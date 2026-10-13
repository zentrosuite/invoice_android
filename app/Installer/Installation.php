<?php

declare(strict_types=1);

namespace App\Installer;

use Throwable;

/**
 * Whether this copy of the application has been set up, and the pre-boot work that makes the
 * installer reachable at all.
 *
 * ── The chicken and egg ─────────────────────────────────────────────────────
 *
 * A freshly cloned repository has no .env. No .env means no APP_KEY, and Laravel's cookie
 * encrypter throws on the first request that touches a session — before routing, so before any
 * controller could show a helpful page. The default SESSION_DRIVER and CACHE_STORE are both
 * `database`, which throws too, because the database is the thing the user has come here to
 * configure.
 *
 * So the installer cannot be "a page you visit". Something has to run before the framework
 * boots. That is bootstrap(), called from bootstrap/app.php ahead of Application::configure().
 * It is deliberately tiny and deliberately silent: create what must exist, change nothing that
 * already does, and never throw — a fatal error here would replace the installer with a white
 * screen, which is the one outcome worth engineering against.
 *
 * ── Why a marker file rather than "does the database have tables" ───────────
 *
 * Checking the database means connecting to it, on every single request, to answer a question
 * whose answer changes once in the lifetime of the install. It also cannot distinguish "not
 * installed yet" from "the database is momentarily down", and answering the second with the
 * installer would invite someone to reinstall over a live system during an outage.
 */
final class Installation
{
    /**
     * Bumped when a future version needs to detect and upgrade an older install.
     */
    public const VERSION = 1;

    /**
     * Test-only override of isInstalled(). The real answer is a file in storage/, which a test
     * must not create or delete on the developer's own checkout.
     */
    private static ?bool $fakeInstalled = null;

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @param  string|null  $basePath  Passed explicitly by bootstrap/app.php, which knows the
     *                                 base path for certain because it sits in it. Inferring it
     *                                 from this file's location works too, and is the fallback
     *                                 for callers that run after the framework has booted.
     */
    public static function make(?string $basePath = null): self
    {
        return new self($basePath ?? dirname(__DIR__, 2));
    }

    public function markerPath(): string
    {
        return $this->basePath . '/storage/app/installed.json';
    }

    public static function fake(?bool $installed): void
    {
        self::$fakeInstalled = $installed;
    }

    public function isInstalled(): bool
    {
        if (self::$fakeInstalled !== null) {
            return self::$fakeInstalled;
        }

        return is_file($this->markerPath());
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        if (! $this->isInstalled()) {
            return [];
        }

        $raw = @file_get_contents($this->markerPath());
        $data = $raw === false ? null : json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public function markInstalled(): void
    {
        if (self::$fakeInstalled !== null) {
            self::$fakeInstalled = true;

            return;
        }

        @file_put_contents($this->markerPath(), json_encode([
            'version' => self::VERSION,
            'installed_at' => date('c'),
        ], JSON_PRETTY_PRINT));
    }

    public function environment(): EnvironmentFile
    {
        return EnvironmentFile::forApplication($this->basePath);
    }

    /**
     * Everything that has to be true before Laravel can boot far enough to render a page.
     */
    public static function bootstrap(?string $basePath = null): void
    {
        try {
            $installation = self::make($basePath);
            $installation->ensureStorageDirectories();

            $env = $installation->environment();

            /*
             * Laravel cannot boot without a writable bootstrap/cache — PackageManifest and
             * ProviderRepository both throw outright, before routing, so no controller and no
             * error page ever runs. There is no env override for that path, which means this
             * is not something the application can work around from inside itself.
             *
             * So it is checked here, ahead of the framework, and answered with a plain HTML
             * page that names the problem and the command that fixes it. A blank 500 is the one
             * outcome worth engineering against, and on a fresh clone served by a web server
             * that did not clone it, this is the FIRST request — not an edge case.
             */
            $installation->blockIfUnbootable();

            if (! $env->exists()) {
                if (! $env->isWritable()) {
                    // storage is fine but the base directory is not, so Laravel can boot.
                    // Run from memory and let the requirements page explain.
                    $installation->degrade();

                    return;
                }

                $env->createFromExample();

                $env->set([
                    'SESSION_DRIVER' => 'file',
                    'CACHE_STORE' => 'file',
                    'QUEUE_CONNECTION' => 'sync',
                ]);
            }

            if ($env->isWritable()) {
                $env->ensureAppKey();
            }

            /*
             * Until setup finishes, sessions and cache live in files whatever .env says. A .env
             * left over from an earlier attempt — or copied from another install — can name the
             * database drivers, and with no tables yet the very first request to the installer
             * would die starting a session. Injected as real environment variables before Laravel
             * reads .env, which never overrides a variable that is already set.
             */
            if (! $installation->isInstalled()) {
                foreach (['SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file'] as $key => $value) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }

            // .env exists, but storage may still belong to someone else — which is the usual
            // state of a fresh clone served by a web server that did not clone it.
            if (! $installation->storageIsUsable()) {
                $installation->degrade();
            }
        } catch (Throwable) {
            // Intentionally silent — see the docblock.
        }
    }

    /**
     * Stop with an explanation when the framework physically cannot start.
     *
     * Only the two directories Laravel itself refuses to run without. Anything softer belongs
     * on the requirements screen, which can render.
     */
    public function blockIfUnbootable(): void
    {
        $fatal = [];

        foreach (['bootstrap/cache', 'storage/framework/views'] as $relative) {
            $path = $this->basePath . '/' . $relative;

            if (! is_dir($path)) {
                @mkdir($path, 0o775, true);
            }

            if (! is_dir($path) || ! is_writable($path)) {
                $fatal[] = $relative;
            }
        }

        if ($fatal === []) {
            return;
        }

        // Nothing below touches the framework: it is not loaded yet and cannot be.
        if (PHP_SAPI !== 'cli') {
            http_response_code(503);
            header('Content-Type: text/html; charset=utf-8');
            header('Retry-After: 60');
            echo $this->blockingPage($fatal);
        } else {
            fwrite(STDERR, "\n  Cannot start: " . implode(', ', $fatal) . " is not writable.\n"
                . '  Run: ' . $this->permissionCommand() . "\n\n");
        }

        exit(1);
    }

    /**
     * Whether this request is running from memory because the filesystem is unwritable.
     *
     * Read through here rather than env() in a controller: env() returns null once the config
     * is cached, and a caller who trusts it would then report a healthy install.
     */
    public function isDegraded(): bool
    {
        return ($_SERVER['ZENTRO_DEGRADED'] ?? $_ENV['ZENTRO_DEGRADED'] ?? getenv('ZENTRO_DEGRADED')) === '1';
    }

    public function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Run the installer from memory when the filesystem will not cooperate.
     *
     * This is the difference between a white screen and a page that says which directory is
     * unwritable and which command fixes it. A fresh clone is owned by whoever cloned it, and
     * the web server is almost never that user — so this is the *normal* first request, not an
     * edge case.
     *
     * Everything set here is injected as an environment variable rather than written to a file,
     * because writing to a file is precisely what is failing. Laravel reads config through
     * env(), so a value put here wins without anything touching the disk:
     *
     *   APP_KEY             ephemeral, so the encrypter stops throwing. Safe because the
     *                       session store below is memory-only — there is nothing persistent
     *                       for a per-request key to fail to decrypt later.
     *   SESSION/CACHE       array. Both default to database, which is the thing not yet set up.
     *   VIEW_COMPILED_PATH  a temp directory, so Blade can compile the page that explains this.
     *   LOG_CHANNEL         stderr, because storage/logs is the directory in question.
     */
    public function degrade(): void
    {
        $compiled = sys_get_temp_dir() . '/zentro-install-views-' . substr(sha1($this->basePath), 0, 12);

        if (! is_dir($compiled)) {
            @mkdir($compiled, 0o777, true);
        }

        $injected = [
            'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'stderr',
            'VIEW_COMPILED_PATH' => $compiled,
            // Read by the requirements screen so it can say plainly that it is running in this
            // mode, rather than looking like a working installation.
            'ZENTRO_DEGRADED' => '1',
        ];

        foreach ($injected as $key => $value) {
            // Only fill gaps: a real .env value that happens to exist must still win.
            if (getenv($key) !== false && getenv($key) !== '') {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    /**
     * Persist the base URL the installer is actually being served from.
     *
     * Refuses to overwrite anything but the shipped placeholder, so a deliberately set value —
     * a public hostname behind a proxy, say — survives a revisit to step one.
     */
    public function captureAppUrl(string $url): void
    {
        $url = rtrim(trim($url), '/');

        if ($url === '' || ! str_starts_with($url, 'http')) {
            return;
        }

        $env = $this->environment();

        if (! $env->exists() || ! $env->isWritable()) {
            return;
        }

        $current = rtrim(trim((string) $env->get('APP_URL')), '/');

        /*
         * "Still a placeholder" is decided by comparing against what .env.example actually
         * ships, not against a hardcoded list. A list gets stale the moment the example file
         * changes — it shipped http://localhost:8000, which an earlier guess at
         * ['http://localhost'] silently failed to match, leaving every generated link pointing
         * at a port nothing was listening on.
         */
        $shipped = rtrim(trim((string) $this->exampleValue('APP_URL')), '/');

        $isPlaceholder = $current === ''
            || ($shipped !== '' && $current === $shipped)
            || preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $current) === 1;

        if (! $isPlaceholder || $current === $url) {
            return;
        }

        $env->set(['APP_URL' => $url]);
    }

    /**
     * The one command that makes this checkout usable by the web server.
     *
     * A single line rather than five, because five lines pasted in the wrong order leaves a
     * half-fixed tree that fails differently. Named after the user PHP is running as, so it is
     * right on a box where that is nginx or apache rather than www-data.
     */
    public function permissionCommand(): string
    {
        if ($this->isWindows()) {
            return sprintf(
                'icacls "%1$s\\storage" "%1$s\\bootstrap\\cache" /grant IIS_IUSRS:(OI)(CI)M /T',
                $this->basePath,
            );
        }

        return sprintf(
            'sudo chown -R %2$s %1$s/storage %1$s/bootstrap/cache %1$s/.env && sudo chmod -R ug+rwX %1$s/storage %1$s/bootstrap/cache',
            $this->basePath,
            $this->webServerUser(),
        );
    }

    /**
     * The user the WEB SERVER runs as — not necessarily this process.
     *
     * Under a web SAPI the two are the same, and that answer is authoritative. From the CLI
     * they are not: reporting the terminal user there would print a command that grants access
     * to the wrong account, which looks like it worked and changes nothing.
     */
    public function webServerUser(): string
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            return $this->serverUser();
        }

        foreach (['www-data', 'nginx', 'apache', 'http', 'httpd'] as $candidate) {
            if (function_exists('posix_getpwnam') && posix_getpwnam($candidate) !== false) {
                return $candidate;
            }
        }

        return $this->isWindows() ? 'IIS_IUSRS' : 'www-data';
    }

    public function serverUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && isset($info['name'])) {
                return (string) $info['name'];
            }
        }

        return get_current_user() ?: 'www-data';
    }

    /**
     * Can the framework actually use storage/ — not just does it exist.
     */
    public function storageIsUsable(): bool
    {
        foreach (['framework/views', 'framework/sessions', 'framework/cache', 'logs'] as $dir) {
            $path = $this->basePath . '/storage/' . $dir;

            if (! is_dir($path) || ! is_writable($path)) {
                return false;
            }
        }

        return is_writable($this->basePath . '/bootstrap/cache');
    }

    /**
     * Recreate the storage tree if a clone or a deploy has left parts of it missing.
     */
    public function ensureStorageDirectories(): void
    {
        $required = [
            'storage/app/private',
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($required as $relative) {
            $path = $this->basePath . '/' . $relative;

            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

    /**
     * Adopt an installation that predates the installer.
     */
    public function adoptIfAlreadySetUp(): bool
    {
        if ($this->isInstalled()) {
            return true;
        }

        if (self::$fakeInstalled !== null) {
            return false;
        }

        $env = $this->environment();

        if (! $env->exists()) {
            return false;
        }

        if (($env->get('DB_DATABASE') ?? '') === '' || ($env->get('DB_USERNAME') ?? '') === '') {
            return false;
        }

        try {
            $configured = \Illuminate\Support\Facades\DB::table('users')->exists()
                && app(\App\Settings\CompanySettings::class)->isConfigured();
        } catch (Throwable) {
            // No database, or no settings table yet. Either way this is not an existing
            // install, and the installer is where the user should be.
            return false;
        }

        if (! $configured) {
            return false;
        }

        $this->markInstalled();

        return true;
    }

    /**
     * A self-contained page. No Blade, no assets, no database — none of it is available.
     *
     * @param  list<string>  $fatal
     */
    private function blockingPage(array $fatal): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $rows = implode('', array_map(
            static fn (string $p): string => '<li><code>' . $e($p) . '</code></li>',
            $fatal,
        ));

        $windows = $this->isWindows();
        $command = $e($this->permissionCommand());
        $user = $e($this->serverUser());
        $base = $e($this->basePath);

        $explain = $windows
            ? 'The account IIS or Apache runs as (<code>' . $user . '</code>) has no write access '
              . 'to these folders. On Windows that is granted with <code>icacls</code>.'
            : 'A fresh clone belongs to whoever cloned it, and the web server runs as a different '
              . 'user (<code>' . $user . '</code>). Granting it access needs root, which is why '
              . 'this cannot be done from this page.';

        return <<<HTML
            <!doctype html>
            <html lang="en"><head><meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Zentro Invoices — setup cannot start</title>
            <style>
              :root{color-scheme:light dark}
              *{box-sizing:border-box}
              body{margin:0;min-height:100vh;display:grid;place-items:center;padding:1.5rem;
                   font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
                   background:#f6f7fb;color:#0f172a}
              .card{max-width:44rem;width:100%;background:#fff;border:1px solid #e2e8f0;
                    border-radius:16px;padding:2rem;box-shadow:0 24px 48px -24px rgba(15,23,42,.25)}
              h1{margin:0 0 .5rem;font-size:1.4rem;letter-spacing:-.02em}
              p{margin:.75rem 0;color:#475569}
              ul{margin:.5rem 0 1rem 1.25rem;padding:0}
              code{background:#f1f5f9;padding:.1rem .35rem;border-radius:4px;
                   font:.9em ui-monospace,SFMono-Regular,Menlo,monospace}
              pre{background:#0f172a;color:#e2e8f0;padding:1rem;border-radius:10px;
                  white-space:pre-wrap;word-break:break-all;overflow-wrap:anywhere;margin:0}
              .muted{font-size:.85rem;color:#64748b;margin-top:1.25rem}
              @media(prefers-color-scheme:dark){
                body{background:#070b14;color:#f1f5f9}
                .card{background:#0f1729;border-color:#1e293b}
                p{color:#94a3b8} code{background:#1e293b;color:#e2e8f0}
              }
            </style></head><body><div class="card">
              <h1>Setup cannot start yet</h1>
              <p>The web server cannot write to these folders, and the application will not run
                 without them:</p>
              <ul>{$rows}</ul>
              <p>{$explain}</p>
              <p>Run this once in a terminal, then reload this page:</p>
              <pre>{$command}</pre>
              <p class="muted">Nothing has been changed on your server. Installation folder:
                 <code>{$base}</code></p>
            </div></body></html>
            HTML;
    }

    /**
     * A raw value out of .env.example, used to recognise an untouched setting.
     */
    private function exampleValue(string $key): ?string
    {
        $path = $this->basePath . '/.env.example';

        if (! is_readable($path)) {
            return null;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), $key . '=')) {
                return trim(substr(ltrim($line), strlen($key) + 1), " \t\"'");
            }
        }

        return null;
    }
}
