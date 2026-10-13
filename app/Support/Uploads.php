<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Where the logo and signature live on the phone.
 *
 * Not public/ as on the website: in the app, public/ is part of the app bundle, which an app
 * update replaces — a logo saved there would vanish with the next version. storage/ survives
 * updates. Pages show the images through the `media` route; PDFs embed them from disk.
 */
final class Uploads
{
    public static function root(): string
    {
        return storage_path('app/uploads');
    }

    /**
     * Absolute path for a stored relative path such as "uploads/company/logo-….png".
     */
    public static function path(string $relative): string
    {
        return self::root() . '/' . ltrim(substr($relative, strlen('uploads/')), '/');
    }

    public static function url(string $relative): string
    {
        return route('media', ['file' => basename($relative)]) . '?v=' . (string) @filemtime(self::path($relative));
    }
}
