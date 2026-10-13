<?php

declare(strict_types=1);

namespace App\Providers;

use App\Installer\Installation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Velzon is Bootstrap 5; the default Tailwind pagination markup renders unstyled.
        Paginator::useBootstrapFive();

        /*
         | In production every generated link — above all the customer's WhatsApp link — uses
         | APP_URL, not whatever Host header a request arrived with. Otherwise a link made while
         | browsing via a temporary or www-less address would be sent to customers as is.
         | Not locally: there the same checkout is reached under several hostnames. And not
         | before setup has finished: until the installer records the real address, APP_URL is
         | the template's placeholder, and pinning to it sends the first visitor to localhost.
         */
        $root = (string) config('app.url');

        if ($this->app->isProduction() && str_starts_with($root, 'http') && Installation::make()->isInstalled()) {
            URL::forceRootUrl($root);

            if (str_starts_with($root, 'https://')) {
                URL::forceScheme('https');
            }
        }

        // @money($amount, $symbol) — Indian digit grouping, see App\Support\Money.
        Blade::directive('money', fn (string $args): string => "<?php echo e(\\App\\Support\\Money::format({$args})); ?>");
    }
}
