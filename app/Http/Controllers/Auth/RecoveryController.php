<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Forgotten password, without email.
 *
 * This installation sends no mail, so "email me a reset link" is not available. Instead the
 * installer showed a one-time recovery code; typing it here sets a new password and issues a
 * replacement code, because the old one has now been used.
 */
final class RecoveryController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function create(): View
    {
        return view('auth.recover');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'recovery_code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $key = 'recover|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'recovery_code' => sprintf('Too many attempts. Try again in %d minutes.', (int) ceil(RateLimiter::availableIn($key) / 60)),
            ]);
        }

        $user = User::query()->firstWhere('email', $data['email']);

        if ($user === null || ! $user->recoveryCodeMatches($data['recovery_code'])) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages([
                'recovery_code' => 'That email and recovery code do not match.',
            ]);
        }

        RateLimiter::clear($key);

        $user->password = $data['password'];
        $newCode = $user->issueRecoveryCode();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('profile.edit')
            ->with('success', 'Password changed. Your old recovery code no longer works — save the new one below.')
            ->with('recovery_code', $newCode);
    }
}
