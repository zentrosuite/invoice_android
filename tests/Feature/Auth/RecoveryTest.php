<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_recovery_code_resets_the_password_and_is_then_replaced(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $code = $user->issueRecoveryCode();
        $user->save();

        // Typed loosely — lower case, no dashes — as someone copying off paper would.
        $response = $this->post(route('recover.store'), [
            'email' => 'owner@example.com',
            'recovery_code' => strtolower(str_replace('-', ' ', $code)),
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('recovery_code');
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass', $user->password));
        $this->assertFalse($user->recoveryCodeMatches($code), 'A used code must stop working.');
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $user->issueRecoveryCode();
        $user->save();

        $this->post(route('recover.store'), [
            'email' => 'owner@example.com',
            'recovery_code' => 'AAAA-AAAA-AAAA-AAAA',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('recovery_code');

        $this->assertGuest();
    }
}
