<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Installer\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an unconfigured install to the installer, and a configured one away from it.
 *
 * Prepended to the web group, so it runs before the session starts — on a fresh upload there is
 * no database to keep a session in. It is also the only thing that closes the installer, whose
 * routes are unauthenticated by necessity.
 */
final class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installation = Installation::make();
        $wantsInstaller = $request->is('install', 'install/*');

        if ($installation->isInstalled() || $installation->adoptIfAlreadySetUp()) {
            if (! $wantsInstaller || $request->routeIs('install.complete')) {
                return $next($request);
            }

            return redirect()->route('login');
        }

        if ($wantsInstaller) {
            return $next($request);
        }

        return redirect()->route('install.requirements');
    }
}
