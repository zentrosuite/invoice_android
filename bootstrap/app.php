<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSetUp;
use App\Http\Middleware\LockWhenIdle;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
| Zentro Invoice — the Android app.
|
| The same Laravel application as the website, running on the phone inside NativePHP with its
| own SQLite database. There is no web installer here: NativePHP creates the database and runs
| the migrations when the app starts, and EnsureSetUp sends a first launch to the welcome
| screen. Sign-in is a 4-digit PIN (LockWhenIdle locks again after a few idle minutes).
*/
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [EnsureSetUp::class, LockWhenIdle::class]);
        // Laravel runs its own sign-in check early; "is the app set up at all?" must come first,
        // or a first launch would be sent to the PIN screen before a PIN exists.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureSetUp::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
