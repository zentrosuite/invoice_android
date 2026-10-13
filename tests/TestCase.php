<?php

declare(strict_types=1);

namespace Tests;

use App\Installer\Installation;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Installed by default; installer tests switch this off. Never touches storage/.
        Installation::fake(true);
    }

    protected function tearDown(): void
    {
        Installation::fake(null);

        parent::tearDown();
    }

    protected function owner(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}
