<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The owner's own login details — not the business, which is the company profile.
 */
final class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:150', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }

    public function recoveryCode(Request $request): RedirectResponse
    {
        $request->validateWithBag('recovery', [
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $code = $user->issueRecoveryCode();
        $user->save();

        return back()
            ->with('success', 'New recovery code issued. The previous one no longer works.')
            ->with('recovery_code', $code);
    }
}
