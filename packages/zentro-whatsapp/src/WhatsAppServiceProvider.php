<?php

declare(strict_types=1);

namespace Zentro\WhatsApp;

use Illuminate\Support\ServiceProvider;

/**
 * Nothing to register on the PHP side: the page calls ZentroWhatsApp.SendFile through the
 * NativePHP bridge directly. The provider exists because NativePHP plugins are Laravel packages.
 */
final class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
