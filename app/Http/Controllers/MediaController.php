<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CompanyImageStore;
use App\Support\Uploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The logo and signature, which live in storage on the phone (see App\Support\Uploads).
 */
final class MediaController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(preg_match(CompanyImageStore::FILE_PATTERN, $file) === 1, 404);

        $path = Uploads::path('uploads/company/' . $file);
        abort_unless(is_file($path), 404);

        $type = str_ends_with($file, '.jpg') ? 'image/jpeg' : 'image/png';

        return response()->file($path, ['Content-Type' => $type, 'Cache-Control' => 'private, max-age=31536000']);
    }
}
