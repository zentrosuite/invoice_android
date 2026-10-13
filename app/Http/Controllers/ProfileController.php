<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Security: change the PIN, issue a new recovery code.
 */
final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function pin(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('pin', [
            'current_pin' => ['required', 'digits:4'],
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [
            'pin.digits' => 'The new PIN must be exactly 4 digits.',
            'pin.confirmed' => 'The two new PINs do not match.',
        ]);

        $this->checkPin($request, $data['current_pin'], 'pin');

        $request->user()->update(['password' => $data['pin']]);

        return back()->with('success', 'PIN changed.');
    }

    public function recoveryCode(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('recovery', ['current_pin' => ['required', 'digits:4']]);

        $this->checkPin($request, $data['current_pin'], 'recovery');

        $user = $request->user();
        $code = $user->issueRecoveryCode();
        $user->save();

        return back()
            ->with('success', 'New recovery code issued. The previous one no longer works.')
            ->with('recovery_code', $code);
    }

    private function checkPin(Request $request, string $pin, string $bag): void
    {
        if (! Hash::check($pin, $request->user()->password)) {
            throw ValidationException::withMessages(['current_pin' => 'That is not your current PIN.'])->errorBag($bag);
        }
    }
}
