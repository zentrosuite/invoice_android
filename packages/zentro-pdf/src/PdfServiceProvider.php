<?php

declare(strict_types=1);

namespace Zentro\Pdf;

use Illuminate\Support\ServiceProvider;

/**
 * Nothing to register on the PHP side: the page calls ZentroPdf.FromHtml through the NativePHP
 * bridge directly. The provider exists because NativePHP plugins are Laravel packages.
 */
final class PdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
