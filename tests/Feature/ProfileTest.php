<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_page_renders(): void
    {
        $this->actingAs($this->owner())->get(route('profile.edit'))->assertOk()->assertSee('Login details');
    }

    public function test_name_and_email_can_be_changed(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->put(route('profile.update'), ['name' => 'New Name', 'email' => 'new@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('new@example.com', $user->refresh()->email);
    }

    public function test_the_password_change_needs_the_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'wrong',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password',
                'password' => 'brand-new-pass',
                'password_confirmation' => 'brand-new-pass',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->refresh()->password));
    }

    public function test_a_new_recovery_code_can_be_issued(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->post(route('profile.recovery'), ['current_password' => 'password'])
            ->assertSessionHas('recovery_code');

        $this->assertTrue($user->refresh()->recoveryCodeMatches(session('recovery_code')));
    }
}
