<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A file the Android document picker copied into the app's cache.
 *
 * The page passes the picker's path back to the server; this is the check that it really is
 * one of those copies — inside the app's own "picked-documents" cache folder — and not any
 * other file on the phone a crafted request might name.
 */
final class PickedFile
{
    public static function resolve(?string $path): ?string
    {
        $path = (string) $path;

        if ($path === '' || str_contains($path, '..') || ! str_contains($path, '/cache/picked-documents/')) {
            return null;
        }

        $real = realpath($path);

        return $real !== false && is_file($real) && str_contains($real, '/cache/picked-documents/') ? $real : null;
    }
}
