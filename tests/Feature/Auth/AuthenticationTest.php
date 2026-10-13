<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_login_page_renders(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sign in');
    }

    public function test_the_owner_can_sign_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'nope'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_repeated_failures_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'nope']);
        }

        // Even the right password is refused until the lockout expires.
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }
}
