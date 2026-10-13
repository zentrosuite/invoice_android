<?php

declare(strict_types=1);

namespace App\Installer;

use RuntimeException;

/**
 * Reads and writes the .env file.
 */
final class EnvironmentFile
{
    public function __construct(
        private readonly string $path,
        private readonly string $examplePath,
    ) {}

    public static function forApplication(string $basePath): self
    {
        return new self($basePath . '/.env', $basePath . '/.env.example');
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Is the directory writable enough to create .env, or the file itself to change it?
     */
    public function isWritable(): bool
    {
        return $this->exists()
            ? is_writable($this->path)
            : is_writable(dirname($this->path));
    }

    /**
     * Create .env from .env.example.
     */
    public function createFromExample(): void
    {
        if ($this->exists()) {
            return;
        }

        if (! is_file($this->examplePath)) {
            throw new RuntimeException(
                'There is no .env.example to copy. The clone looks incomplete — try cloning again.',
            );
        }

        $contents = file_get_contents($this->examplePath);

        if ($contents === false) {
            throw new RuntimeException('.env.example exists but could not be read. Check its permissions.');
        }

        $this->put($contents);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $out = [];

        foreach ($this->lines() as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $parts = explode('=', $trimmed, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $out[trim($parts[0])] = $this->unquote(trim($parts[1]));
        }

        return $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Set one or more keys, replacing them in place or appending if absent.
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public function set(array $values): void
    {
        if (! $this->exists()) {
            $this->createFromExample();
        }

        $lines = $this->lines();
        $remaining = $values;

        foreach ($lines as $index => $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $key = trim(explode('=', $trimmed, 2)[0]);

            if (! array_key_exists($key, $remaining)) {
                continue;
            }

            $lines[$index] = $key . '=' . $this->quote($remaining[$key]);
            unset($remaining[$key]);
        }

        // Anything the template did not already carry goes on the end, under a heading, so an
        // installed .env still reads like a document rather than a pile of appended lines.
        if ($remaining !== []) {
            $lines[] = '';
            $lines[] = '# Added by the installer';

            foreach ($remaining as $key => $value) {
                $lines[] = $key . '=' . $this->quote($value);
            }
        }

        $this->put(implode("\n", $lines) . "\n");
    }

    /**
     * An APP_KEY in the format Laravel expects, generated only if one is not already set.
     */
    public function ensureAppKey(): string
    {
        $existing = $this->get('APP_KEY', '') ?? '';

        if ($existing !== '' && $existing !== 'base64:') {
            return $existing;
        }

        $key = 'base64:' . base64_encode(random_bytes(32));
        $this->set(['APP_KEY' => $key]);

        return $key;
    }

    /**
     * @return list<string>
     */
    private function lines(): array
    {
        $contents = $this->exists() ? file_get_contents($this->path) : '';

        if ($contents === false) {
            throw new RuntimeException("Could not read {$this->path}. Check its permissions.");
        }

        return explode("\n", rtrim(str_replace("\r\n", "\n", $contents), "\n"));
    }

    /**
     * Quote a value so phpdotenv reads back exactly what was put in.
     */
    private function quote(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        if (preg_match('/\A[A-Za-z0-9_.\-\/:]+\z/', $value) === 1) {
            return $value;
        }

        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }

    private function unquote(string $value): string
    {
        // Strip an inline comment only when the value is not quoted — inside quotes a # is
        // part of the password, which is exactly the case that makes this worth getting right.
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end = strrpos($value, $quote);

            if ($end !== false && $end > 0) {
                $inner = substr($value, 1, $end - 1);

                return $quote === '"'
                    ? str_replace(['\\"', '\\$', '\\\\'], ['"', '$', '\\'], $inner)
                    : $inner;
            }
        }

        $hash = strpos($value, ' #');

        return $hash === false ? $value : rtrim(substr($value, 0, $hash));
    }

    /**
     * Write atomically: temp file in the same directory, then rename over the target.
     */
    private function put(string $contents): void
    {
        $directory = dirname($this->path);

        if (! is_writable($this->exists() ? $this->path : $directory)) {
            throw new RuntimeException(sprintf(
                'Cannot write %s. Give the web server user permission to write to %s.',
                basename($this->path),
                $directory,
            ));
        }

        $temp = tempnam($directory, '.env.tmp');

        if ($temp === false || file_put_contents($temp, $contents) === false) {
            throw new RuntimeException("Could not write a temporary file in {$directory}.");
        }

        // tempnam() creates at 0600; .env must stay readable by the web server user, which is
        // usually the owner here, but 0640 keeps it off-limits to everyone else.
        @chmod($temp, 0640);

        if (! rename($temp, $this->path)) {
            @unlink($temp);

            throw new RuntimeException("Could not replace {$this->path}.");
        }
    }
}
