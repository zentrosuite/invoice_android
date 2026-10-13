<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The PIN screen. One user, so there is nothing to type but the 4 digits.
 */
final class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const THROTTLE_KEY = 'pin-unlock';

    public function create(): View
    {
        return view('auth.pin');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'digits:4']], ['pin.digits' => 'Enter your 4-digit PIN.']);

        if (RateLimiter::tooManyAttempts(self::THROTTLE_KEY, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => sprintf('Too many wrong PINs. Try again in %d seconds.', RateLimiter::availableIn(self::THROTTLE_KEY)),
            ]);
        }

        $user = User::query()->first();

        if ($user === null || ! Hash::check($data['pin'], $user->password)) {
            RateLimiter::hit(self::THROTTLE_KEY, 60);

            throw ValidationException::withMessages(['pin' => 'Wrong PIN.']);
        }

        RateLimiter::clear(self::THROTTLE_KEY);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity', time());

        return redirect()->intended(route('dashboard'));
    }

    /**
     * "Lock": back to the PIN screen.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
