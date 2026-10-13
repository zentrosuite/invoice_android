<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Uploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The logo and signature, which live in storage on the phone (see App\Support\Uploads).
 */
final class MediaController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^(logo|signature)-\d+\.png$/', $file) === 1, 404);

        $path = Uploads::path('uploads/company/' . $file);
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=31536000']);
    }
}
