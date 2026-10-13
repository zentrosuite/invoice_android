<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First launch: until the owner has chosen a profile, named the business and set a PIN, every
 * page leads to the welcome screens — and once they have, the welcome screens are closed.
 */
final class EnsureSetUp
{
    public function handle(Request $request, Closure $next): Response
    {
        // NativePHP's own bridge endpoints and the launch animation are never redirected.
        if ($request->is('_native/*', 'launch')) {
            return $next($request);
        }

        $setUp = User::query()->exists();
        $welcoming = $request->routeIs('welcome', 'welcome.*');

        if (! $setUp && ! $welcoming) {
            return redirect()->route('welcome');
        }

        // The recovery-code screen is the last welcome step and must stay reachable once.
        if ($setUp && $welcoming && ! $request->routeIs('welcome.done')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
