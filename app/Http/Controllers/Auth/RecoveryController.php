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
use Illuminate\Validation\ValidationException;

/**
 * Forgotten PIN: the recovery code shown at setup sets a new PIN, and a replacement code is
 * issued because the old one has now been used.
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
            'recovery_code' => ['required', 'string'],
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [
            'pin.digits' => 'The PIN must be exactly 4 digits.',
            'pin.confirmed' => 'The two PINs do not match.',
        ]);

        $key = 'pin-recover';

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'recovery_code' => sprintf('Too many attempts. Try again in %d minutes.', (int) ceil(RateLimiter::availableIn($key) / 60)),
            ]);
        }

        $user = User::query()->first();

        if ($user === null || ! $user->recoveryCodeMatches($data['recovery_code'])) {
            RateLimiter::hit($key, 15 * 60);

            throw ValidationException::withMessages(['recovery_code' => 'That recovery code is not right.']);
        }

        RateLimiter::clear($key);

        $user->password = $data['pin'];
        $newCode = $user->issueRecoveryCode();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity', time());

        return redirect()->route('profile.edit')
            ->with('success', 'PIN changed. Your old recovery code no longer works — save the new one below.')
            ->with('recovery_code', $newCode);
    }
}
