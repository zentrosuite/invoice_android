<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UsageProfile;
use App\Installer\Installation;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_uninstalled_app_sends_every_page_to_the_installer(): void
    {
        Installation::fake(false);

        $this->get('/')->assertRedirect(route('install.requirements'));
        $this->get('/login')->assertRedirect(route('install.requirements'));
    }

    public function test_an_installed_app_closes_the_installer(): void
    {
        $this->get('/install/business')->assertRedirect(route('login'));
        $this->post('/install/account', [])->assertRedirect(route('login'));
    }

    public function test_the_business_step_waits_for_the_tables(): void
    {
        Installation::fake(false);

        $this->get(route('install.business'))->assertRedirect(route('install.migrate'));
    }

    public function test_the_business_step_lists_every_usage_profile(): void
    {
        Installation::fake(false);

        $response = $this->withSession(['install.migrated' => true])->get(route('install.business'));

        $response->assertOk();
        foreach (UsageProfile::cases() as $profile) {
            $response->assertSee($profile->label());
        }
    }

    public function test_a_usage_profile_is_required(): void
    {
        Installation::fake(false);

        $this->withSession(['install.migrated' => true])
            ->post(route('install.business.store'), ['business_name' => 'Rasi Stores'])
            ->assertSessionHasErrors('usage_profile');
    }

    public function test_finishing_setup_creates_the_owner_and_applies_the_profile(): void
    {
        Installation::fake(false);

        $this->withSession(['install.migrated' => true])
            ->post(route('install.business.store'), [
                'usage_profile' => 'freelancer',
                'business_name' => 'Zentro Private Limited',
                'phone' => '+91 80565 56341',
            ])
            ->assertRedirect(route('install.account'));

        $response = $this->post(route('install.account.store'), [
            'name' => 'Fawwash Khan',
            'email' => 'owner@example.com',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ]);

        $response->assertRedirect(route('install.complete'));
        $response->assertCookie('zi_setup_complete');

        $user = User::sole();
        $this->assertSame('Fawwash Khan', $user->name);
        $this->assertNotNull($user->recovery_code);

        $company = app(CompanySettings::class);
        $this->assertSame('Zentro Private Limited', $company->name);
        $this->assertSame('+91 80565 56341', $company->phone);

        $invoice = app(InvoiceSettings::class);
        $this->assertSame(UsageProfile::Freelancer, $invoice->profile());
        $this->assertFalse($invoice->showQuantity);
        $this->assertSame('Service description', $invoice->itemLabel);

        $this->assertTrue(Installation::make()->isInstalled());
    }

    public function test_the_completion_page_shows_the_recovery_code_once(): void
    {
        $code = 'ABCD-EFGH-JKLM-NPQR';

        $this->withCookie('zi_setup_complete', json_encode(['email' => 'owner@example.com', 'code' => $code]))
            ->get(route('install.complete'))
            ->assertOk()
            ->assertSee($code)
            ->assertCookieExpired('zi_setup_complete');
    }
}
