<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UsageProfile;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * First launch: how the app will be used, the business name, then a PIN.
 *
 * Nothing is written until the PIN is set — the business details wait in the session, so
 * closing the app half way through simply starts the welcome again.
 */
final class WelcomeController extends Controller
{
    private const SESSION_BUSINESS = 'welcome.business';

    public function business(Request $request): View
    {
        return view('welcome.business', [
            'profiles' => UsageProfile::cases(),
            'saved' => (array) $request->session()->get(self::SESSION_BUSINESS, []),
        ]);
    }

    public function storeBusiness(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'usage_profile' => ['required', Rule::enum(UsageProfile::class)],
            'business_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
        ], [
            'usage_profile.required' => 'Choose how you will use Zentro Invoice.',
            'phone.regex' => 'Use digits, spaces, + and - only.',
        ]);

        $request->session()->put(self::SESSION_BUSINESS, $data);

        return redirect()->route('welcome.pin');
    }

    public function pin(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_BUSINESS)) {
            return redirect()->route('welcome');
        }

        return view('welcome.pin');
    }

    public function storePin(Request $request): RedirectResponse
    {
        $business = (array) $request->session()->get(self::SESSION_BUSINESS, []);

        if ($business === []) {
            return redirect()->route('welcome');
        }

        $data = $request->validate([
            'pin' => ['required', 'digits:4', 'confirmed'],
        ], [
            'pin.digits' => 'The PIN must be exactly 4 digits.',
            'pin.confirmed' => 'The two PINs do not match.',
        ]);

        $code = DB::transaction(function () use ($business, $data): string {
            // The app's one user. There is no email on a phone app; the PIN is the password.
            $user = new User;
            $user->name = 'Owner';
            $user->email = 'owner@zentro.app';
            $user->password = $data['pin'];
            $code = $user->issueRecoveryCode();
            $user->save();

            $company = app(CompanySettings::class);
            $company->name = trim($business['business_name']);
            $company->phone = isset($business['phone']) && trim((string) $business['phone']) !== '' ? trim($business['phone']) : null;
            $company->save();

            $invoice = app(InvoiceSettings::class);
            $invoice->applyProfile(UsageProfile::from($business['usage_profile']));
            $invoice->save();

            Auth::login($user);

            return $code;
        });

        $request->session()->forget(self::SESSION_BUSINESS);
        $request->session()->regenerate();

        return redirect()->route('welcome.done')->with('recovery_code', $code);
    }

    public function done(Request $request): View|RedirectResponse
    {
        $code = $request->session()->get('recovery_code');

        return $code ? view('welcome.done', ['recoveryCode' => $code]) : redirect()->route('dashboard');
    }
}
