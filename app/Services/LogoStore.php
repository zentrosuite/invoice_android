<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Stores the company logo under public/uploads.
 *
 * public/ rather than storage/app/public: that needs `php artisan storage:link`, a symlink
 * InfinityFree cannot create. A file under public/ is served by the web server directly and is
 * also a plain local path for the PDF renderer.
 *
 * Every upload is re-encoded as PNG through GD. That shrinks a 4000px phone photo to something a
 * PDF can carry, keeps transparency, and means the file on disk is always an image this code
 * wrote — never the uploaded bytes, whatever they claimed to be. dompdf cannot draw WebP, which
 * is the other reason nothing is stored as uploaded.
 */
final class LogoStore
{
    public const DIRECTORY = 'uploads/company';

    private const MAX_WIDTH = 800;

    private const MAX_HEIGHT = 300;

    /**
     * @return string The path relative to public/, e.g. "uploads/company/logo-20261013120000.png".
     */
    public function store(UploadedFile $file, ?string $replacing): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $directory = public_path(self::DIRECTORY);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The uploads folder could not be created.');
        }

        $relative = self::DIRECTORY . '/logo-' . now()->format('YmdHis') . '.png';

        if (! imagepng($canvas, public_path($relative), 9)) {
            throw new RuntimeException('The logo could not be saved.');
        }

        $this->delete($replacing);

        return $relative;
    }

    public function delete(?string $relative): void
    {
        // Only ever inside our own folder, whatever the stored value says.
        if ($relative !== null && str_starts_with($relative, self::DIRECTORY . '/') && ! str_contains($relative, '..')) {
            @unlink(public_path($relative));
        }
    }
}
