<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms are sent with fetch (app.js), not as native form posts. Inside the Android app a native
 * post can lose its body on the way to PHP, and NativePHP follows the redirect itself, so the
 * page is left showing the form's URL — a later back or refresh then lands on a POST-only route.
 *
 * For a fetch-sent form (X-Zentro-Form) a redirect — success, or validation errors sent back —
 * becomes {"redirect": url}, and the page navigates there itself. The flash data and errors are
 * already in the session, exactly as for a normal redirect.
 */
final class FormRedirectsAsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->has('X-Zentro-Form') && $response instanceof RedirectResponse) {
            $json = new JsonResponse(['redirect' => $response->getTargetUrl()]);

            // This runs outside the session and cookie middleware, so the redirect already carries
            // the session cookie (a new one after sign-in) — it must travel with the answer.
            foreach ($response->headers->getCookies() as $cookie) {
                $json->headers->setCookie($cookie);
            }

            return $json;
        }

        return $response;
    }
}
