<?php

declare(strict_types=1);

namespace Zentro\SystemBars;

use Illuminate\Support\ServiceProvider;

/**
 * Nothing to register on the PHP side: the page calls ZentroSystemBars.SetStyle through the
 * NativePHP bridge directly. The provider exists because NativePHP plugins are Laravel packages.
 */
final class SystemBarsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
