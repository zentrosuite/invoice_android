<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Asks for the PIN again after the app has sat unused for a few minutes.
 *
 * Measured between requests: every page view or action refreshes it, so the PIN is only
 * asked for when the phone was put down — picking it back up later, or reopening the app,
 * lands on the PIN screen.
 */
final class LockWhenIdle
{
    public const IDLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! $request->is('_native/*')) {
            $last = (int) $request->session()->get('last_activity', 0);

            if ($last > 0 && time() - $last > self::IDLE_MINUTES * 60) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }

            $request->session()->put('last_activity', time());
        }

        return $next($request);
    }
}
