<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Uploads;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Stores the company's images — logo and signature — under storage/app/uploads (see Uploads).
 *
 * The phone's PHP has no GD. There, the page has already drawn the picture onto white and
 * scaled it to print size as a JPEG (ZentroNative.imageAsJpeg), and it is stored as it came,
 * once getimagesize() confirms it is a JPEG within the size. A JPEG is also the one picture
 * format the PDF renderer can embed without GD.
 *
 * Where GD exists (tests, a desktop build), every upload is re-encoded as PNG through GD. That shrinks a 4000px phone photo to something a
 * PDF can carry, keeps transparency, and means the file on disk is always an image this code
 * wrote — never the uploaded bytes, whatever they claimed to be. dompdf cannot draw WebP, which
 * is the other reason nothing is stored as uploaded.
 */
final class CompanyImageStore
{
    public const DIRECTORY = 'uploads/company';

    /** File names this store writes, e.g. "logo-20261013120000.jpg". */
    public const FILE_PATTERN = '/^(logo|signature)-\d+\.(png|jpg)$/';

    private readonly bool $gd;

    public function __construct(?bool $gd = null)
    {
        $this->gd = $gd ?? function_exists('imagecreatefromstring');
    }

    /**
     * @param  string  $name  File name stem: "logo", "signature".
     * @return string The stored path, e.g. "uploads/company/logo-20261013120000.png" (".jpg" without GD).
     */
    public function store(UploadedFile $file, ?string $replacing, string $name = 'logo', int $maxWidth = 800, int $maxHeight = 300): string
    {
        if (! $this->gd) {
            return $this->storeJpeg($file, $replacing, $name, $maxWidth, $maxHeight);
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $relative = self::DIRECTORY . '/' . $name . '-' . now()->format('YmdHis') . '.png';
        $this->ensureDirectory();

        if (! imagepng($canvas, Uploads::path($relative), 9)) {
            throw new RuntimeException('The image could not be saved.');
        }

        $this->delete($replacing);

        return $relative;
    }

    /** Without GD: keep a JPEG that is already at print size, exactly as it came. */
    private function storeJpeg(UploadedFile $file, ?string $replacing, string $name, int $maxWidth, int $maxHeight): string
    {
        $info = @getimagesize($file->getRealPath());

        if ($info === false || $info[2] !== IMAGETYPE_JPEG) {
            throw new RuntimeException('That picture could not be read. Choose it again.');
        }

        // The page scales to fit; one pixel of rounding is allowed.
        if ($info[0] > $maxWidth + 1 || $info[1] > $maxHeight + 1) {
            throw new RuntimeException('That picture is too large. Choose it again.');
        }

        $relative = self::DIRECTORY . '/' . $name . '-' . now()->format('YmdHis') . '.jpg';
        $this->ensureDirectory();

        if (! copy($file->getRealPath(), Uploads::path($relative))) {
            throw new RuntimeException('The image could not be saved.');
        }

        $this->delete($replacing);

        return $relative;
    }

    private function ensureDirectory(): void
    {
        $directory = Uploads::path(self::DIRECTORY);

        if (! is_dir($directory) && ! mkdir($directory, 0o775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The uploads folder could not be created.');
        }
    }

    public function delete(?string $relative): void
    {
        // Only ever inside our own folder, whatever the stored value says.
        if ($relative !== null && str_starts_with($relative, self::DIRECTORY . '/') && ! str_contains($relative, '..')) {
            @unlink(Uploads::path($relative));
        }
    }
}
