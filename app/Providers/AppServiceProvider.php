<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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

        // @money($amount, $symbol) — Indian digit grouping, see App\Support\Money.
        Blade::directive('money', fn (string $args): string => "<?php echo e(\\App\\Support\\Money::format({$args})); ?>");
    }
}
