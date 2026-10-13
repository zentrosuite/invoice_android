<?php

declare(strict_types=1);

use App\Http\Middleware\RedirectIfNotInstalled;
use App\Installer\Installation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
| Runs before the framework is configured, and has to: a fresh upload has no .env, so no
| APP_KEY, and the default session and cache drivers point at the database the user has come
| to set up. This creates .env from the template, generates a key and pins sessions to files
| until the installer has a database. Cheap, idempotent, and never throws.
*/
Installation::bootstrap(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Applies its own 'web' middleware inside the file.
            require base_path('routes/install.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [RedirectIfNotInstalled::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
