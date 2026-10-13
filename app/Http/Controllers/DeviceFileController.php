<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PickedFile;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a just-picked file back to the page — how a chosen photo reaches the canvas that
 * turns it into a PNG (the phone's PHP has no JPEG support, the WebView does).
 */
final class DeviceFileController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $path = PickedFile::resolve($request->query('path'));

        abort_if($path === null, 404);

        return response()->file($path, ['Cache-Control' => 'no-store']);
    }
}
