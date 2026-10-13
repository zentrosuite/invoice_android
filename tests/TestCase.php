<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function owner(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}
