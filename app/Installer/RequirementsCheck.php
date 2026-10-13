<?php

declare(strict_types=1);

namespace App\Installer;

/**
 * What the server has to provide before this application will run.
 */
final class RequirementsCheck
{
    /**
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'pdo_mysql' => 'Talking to MySQL. There is no other supported database.',
        'mbstring' => 'Handling ₹ and any non-ASCII text without corrupting it.',
        'openssl' => 'Encrypting sessions and cookies.',
        'tokenizer' => 'Compiling Blade templates.',
        'xml' => 'Generating invoice PDFs.',
        'ctype' => 'String validation throughout the framework.',
        'json' => 'Settings storage and every API response.',
        'bcmath' => 'Exact decimal arithmetic. Money must never touch a float.',
        'fileinfo' => 'Verifying that an uploaded logo really is an image.',
        'gd' => 'Drawing the logo and payment QR into invoice PDFs.',
    ];

    public function __construct(
        private readonly string $basePath,
    ) {}

    public static function make(): self
    {
        return new self(dirname(__DIR__, 2));
    }

    /**
     * @return array{
     *     php: array<string, mixed>,
     *     extensions: list<array<string, mixed>>,
     *     paths: list<array<string, mixed>>,
     *     satisfied: bool,
     *     failureCount: int,
     * }
     */
    public function run(): array
    {
        $php = $this->php();
        $extensions = $this->extensions();
        $paths = $this->paths();

        $failures = ($php['ok'] ? 0 : 1)
            + count(array_filter($extensions, static fn (array $e): bool => ! $e['ok']))
            + count(array_filter($paths, static fn (array $p): bool => ! $p['ok']));

        return [
            'php' => $php,
            'extensions' => $extensions,
            'paths' => $paths,
            'satisfied' => $failures === 0,
            'failureCount' => $failures,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function php(): array
    {
        // Matches composer.json. 8.3 because that is the newest PHP InfinityFree offers.
        $required = '8.3.0';

        return [
            'label' => 'PHP ' . $required . ' or newer',
            'current' => PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, $required, '>='),
            'hint' => 'Switch the site to PHP 8.3 or newer in your hosting control panel.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extensions(): array
    {
        $out = [];
        $missing = [];

        foreach (self::EXTENSIONS as $name => $why) {
            $loaded = extension_loaded($name);

            if (! $loaded) {
                $missing[] = $name;
            }

            $out[] = [
                'label' => 'ext-' . $name,
                'why' => $why,
                'ok' => $loaded,
            ];
        }

        // One command that installs everything missing beats thirteen rows each suggesting
        // their own. Built from what is actually absent, so it is copy-pasteable as-is.
        if ($missing !== []) {
            $command = 'sudo apt install ' . implode(' ', array_map(
                static fn (string $e): string => 'php8.3-' . str_replace('pdo_', '', $e),
                $missing,
            ));

            foreach ($out as $index => $row) {
                if (! $row['ok']) {
                    $out[$index]['hint'] = $command;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paths(): array
    {
        $targets = [
            '.env' => 'The installer writes your database settings here.',
            'storage/framework' => 'Compiled templates and session files.',
            'storage/logs' => 'Where errors go. Without it, failures are invisible.',
            'bootstrap/cache' => 'Cached configuration and routes.',
        ];

        $out = [];

        foreach ($targets as $relative => $why) {
            $absolute = $this->basePath . '/' . $relative;

            // .env may not exist yet — in that case the question is whether the directory
            // holding it can be written to, which is what actually has to be true.
            $probe = ($relative === '.env' && ! file_exists($absolute))
                ? dirname($absolute)
                : $absolute;

            $writable = file_exists($probe) && is_writable($probe);

            $out[] = [
                'label' => $relative,
                'why' => $why,
                'ok' => $writable,
                // One command for every path, rather than a different chown per row: five
                // lines pasted in the wrong order leaves a half-fixed tree that fails
                // differently, and the command below is idempotent.
                'hint' => Installation::make($this->basePath)->permissionCommand(),
            ];
        }

        return $out;
    }
}
