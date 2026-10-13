<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The app's one user, with PIN 1234, already through the welcome screens.
     */
    protected function owner(): User
    {
        return User::factory()->create(['email' => 'owner@zentro.app', 'password' => '1234']);
    }
}
