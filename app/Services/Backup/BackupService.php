<?php

declare(strict_types=1);

namespace App\Services\Backup;

use App\Services\CompanyImageStore;
use App\Support\DeviceFiles;
use App\Support\Uploads;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Everything the app knows, in one file the owner can keep somewhere other than this phone.
 *
 * The data lives only on the phone: uninstalling the app, resetting or losing the phone takes
 * it with it. A backup is the database plus the uploaded logo and signature, as gzip-compressed
 * JSON with a .zentrobackup extension — no zip extension needed, which the phone's PHP lacks.
 *
 *   { "format": "zentro-invoice-backup", "version": 1, "created_at": …, "app_version": …,
 *     "database": "<base64 of the SQLite file>", "files": { "uploads/company/logo-….jpg": "<base64>" } }
 */
final class BackupService
{
    public const FORMAT = 'zentro-invoice-backup';

    public const VERSION = 1;

    /** Generous: a year of a busy shop's invoices is a few MB. */
    private const MAX_BYTES = 200 * 1024 * 1024;

    /**
     * Write a backup file and return [path, filename].
     *
     * @return array{0: string, 1: string}
     */
    public function create(): array
    {
        // A consistent snapshot of the live database, taken by SQLite itself rather than by
        // copying a file another connection may be writing to.
        $snapshot = DeviceFiles::path('backup', 'snapshot.sqlite');
        @unlink($snapshot);
        DB::statement('VACUUM INTO ?', [$snapshot]);

        $files = [];
        foreach (glob(Uploads::path('uploads/company') . '/*') ?: [] as $file) {
            if (preg_match(CompanyImageStore::FILE_PATTERN, basename($file)) !== 1) {
                continue;
            }
            $files['uploads/company/' . basename($file)] = base64_encode((string) file_get_contents($file));
        }

        $payload = json_encode([
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'created_at' => now()->toIso8601String(),
            'app_version' => config('nativephp.version'),
            'database' => base64_encode((string) file_get_contents($snapshot)),
            'files' => $files,
        ], JSON_THROW_ON_ERROR);

        @unlink($snapshot);

        $name = 'Zentro-Invoice-backup-' . now()->format('Y-m-d-Hi') . '.zentrobackup';
        $path = DeviceFiles::path('backup', $name);
        file_put_contents($path, gzencode($payload, 6));

        return [$path, $name];
    }

    /**
     * Replace this phone's data with a backup. Validates everything before touching anything.
     */
    public function restore(string $path): void
    {
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new RuntimeException('That file could not be read.');
        }

        $json = @gzdecode((string) file_get_contents($path));
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('That is not a Zentro Invoice backup file.');
        }

        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            throw new RuntimeException('That backup was made by a newer version of the app. Update the app first.');
        }

        $database = base64_decode((string) ($data['database'] ?? ''), true);

        if ($database === false || ! str_starts_with($database, "SQLite format 3\0")) {
            throw new RuntimeException('The backup file is damaged.');
        }

        // Swap the database file. The connection is closed first so SQLite is not holding the
        // old file, then reopened and migrated: a backup from an older app version gains any
        // newer tables and columns.
        $live = (string) config('database.connections.sqlite.database');
        DB::disconnect();
        file_put_contents($live . '.restoring', $database);
        rename($live . '.restoring', $live);
        @unlink($live . '-wal');
        @unlink($live . '-shm');
        DB::reconnect();
        Artisan::call('migrate', ['--force' => true]);

        $dir = Uploads::path('uploads/company');
        if (! is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }
        foreach (glob($dir . '/*') ?: [] as $old) {
            if (preg_match(CompanyImageStore::FILE_PATTERN, basename($old)) === 1) {
                @unlink($old);
            }
        }
        foreach ((array) ($data['files'] ?? []) as $relative => $content) {
            // Only plain file names inside uploads/company — never a path from the file itself.
            $name = basename((string) $relative);
            if (preg_match(CompanyImageStore::FILE_PATTERN, $name) === 1) {
                file_put_contents($dir . '/' . $name, base64_decode((string) $content, true) ?: '');
            }
        }
    }
}
