<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Files the app hands to Android — PDFs to share or save, exports, backups.
 *
 * Kept under storage/app/device/<kind>, one file per kind and name, and each kind folder is
 * emptied of anything older than a day on the next write, so shared files do not pile up on
 * the phone.
 */
final class DeviceFiles
{
    public static function path(string $kind, string $name): string
    {
        $dir = storage_path('app/device/' . preg_replace('/[^a-z0-9_-]/i', '', $kind));

        if (! is_dir($dir)) {
            mkdir($dir, 0o775, true);
        }

        foreach (glob($dir . '/*') ?: [] as $old) {
            if (is_file($old) && filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }

        return $dir . '/' . str_replace(['/', '\\'], '_', $name);
    }
}
