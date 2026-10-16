<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UsageProfile;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PinTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_launch_goes_to_the_welcome_screen(): void
    {
        $this->get('/')->assertRedirect(route('welcome'));
        $this->get(route('login'))->assertRedirect(route('welcome'));
        $this->get(route('welcome'))->assertOk()->assertSee('Welcome to Zentro Invoice');
    }

    public function test_welcome_sets_up_the_business_and_the_pin(): void
    {
        $this->post(route('welcome.business'), ['usage_profile' => 'shop', 'business_name' => 'NuNu Traders', 'phone' => '85264 48260'])
            ->assertRedirect(route('welcome.pin'));

        $this->post(route('welcome.pin.store'), ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertRedirect(route('welcome.done'));

        $user = User::sole();
        $this->assertTrue(Hash::check('1234', $user->password));
        $this->assertNotNull($user->recovery_code);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('NuNu Traders', app(CompanySettings::class)->name);
        $this->assertSame(UsageProfile::Shop, app(InvoiceSettings::class)->profile());

        $this->get(route('welcome.done'))->assertOk()->assertSee('recovery code');
        // Set up: the welcome screens are closed.
        $this->get(route('welcome'))->assertRedirect(route('dashboard'));
    }

    public function test_mismatched_pins_are_refused(): void
    {
        $this->withSession(['welcome.business' => ['usage_profile' => 'shop', 'business_name' => 'X']])
            ->post(route('welcome.pin.store'), ['pin' => '1234', 'pin_confirmation' => '4321'])
            ->assertSessionHasErrors('pin');

        $this->assertSame(0, User::count());
    }

    public function test_the_pin_unlocks_and_a_wrong_one_does_not(): void
    {
        $user = $this->owner();

        $this->get('/')->assertRedirect(route('login'));

        $this->post(route('login.store'), ['pin' => '9999'])->assertSessionHasErrors(['pin' => 'Wrong PIN.']);
        $this->assertGuest();

        $this->post(route('login.store'), ['pin' => '1234'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_five_wrong_pins_lock_the_screen_for_a_minute(): void
    {
        $this->owner();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['pin' => '0000']);
        }

        $this->post(route('login.store'), ['pin' => '1234'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_the_app_locks_after_being_idle(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->withSession(['last_activity' => time() - 21 * 60])
            ->get(route('invoices.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_the_app_stays_open_for_twenty_minutes_without_use(): void
    {
        $this->actingAs($this->owner())
            ->withSession(['last_activity' => time() - 19 * 60])
            ->get(route('invoices.index'))
            ->assertOk();

        $this->assertAuthenticated();
    }

    public function test_the_recovery_code_sets_a_new_pin(): void
    {
        $user = $this->owner();
        $code = $user->issueRecoveryCode();
        $user->save();

        $this->post(route('recover.store'), ['recovery_code' => strtolower($code), 'pin' => '5678', 'pin_confirmation' => '5678'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('recovery_code');

        $user->refresh();
        $this->assertTrue(Hash::check('5678', $user->password));
        $this->assertFalse($user->recoveryCodeMatches($code), 'A used code stops working.');
    }

    public function test_the_pin_can_be_changed_with_the_current_one(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->put(route('profile.pin'), ['current_pin' => '0000', 'pin' => '5678', 'pin_confirmation' => '5678'])
            ->assertSessionHasErrorsIn('pin', 'current_pin');

        $this->actingAs($user)->put(route('profile.pin'), ['current_pin' => '1234', 'pin' => '5678', 'pin_confirmation' => '5678'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('5678', $user->refresh()->password));
    }

    public function test_a_new_recovery_code_needs_the_pin(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post(route('profile.recovery'), ['current_pin' => '1234'])->assertSessionHas('recovery_code');
        $this->assertTrue($user->refresh()->recoveryCodeMatches(session('recovery_code')));
    }

    public function test_the_first_welcome_screen_offers_to_restore_a_backup(): void
    {
        $this->get(route('welcome'))->assertOk()
            ->assertSee('Moving to a new phone?')
            ->assertSee('Restore from a backup file');
    }

    public function test_welcome_restore_rejects_files_the_picker_did_not_provide(): void
    {
        $this->postJson(route('welcome.restore'), ['path' => '/etc/passwd'])
            ->assertStatus(422)->assertJson(['error' => 'That file could not be opened.']);
    }

    public function test_welcome_restore_rejects_a_file_that_is_not_a_backup(): void
    {
        $dir = sys_get_temp_dir() . '/zi-test-' . bin2hex(random_bytes(4)) . '/cache/picked-documents';
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/holiday.jpg', 'not a backup');

        $this->postJson(route('welcome.restore'), ['path' => $dir . '/holiday.jpg'])
            ->assertStatus(422)->assertJson(['error' => 'That is not a Zentro Invoice backup file.']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_welcome_restore_is_closed_once_the_phone_is_set_up(): void
    {
        User::factory()->create();

        $this->postJson(route('welcome.restore'), ['path' => '/tmp/x'])->assertRedirect(route('dashboard'));
    }
}
