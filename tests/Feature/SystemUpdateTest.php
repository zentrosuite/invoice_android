<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PendingMigrations;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_versions_migration_is_offered_and_applied(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->get(route('dashboard'))->assertDontSee('Apply update');

        // A migration "shipped in a new version": a file the database has not run.
        $dir = sys_get_temp_dir() . '/zi-migrations-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/2099_01_01_000000_create_zi_update_probe.php', <<<'PHP'
            <?php
            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;
            return new class extends Migration {
                public function up(): void { Schema::create('zi_update_probe', fn (Blueprint $t) => $t->id()); }
                public function down(): void { Schema::dropIfExists('zi_update_probe'); }
            };
            PHP);
        $this->app['migrator']->path($dir);

        $this->assertContains('2099_01_01_000000_create_zi_update_probe', app(PendingMigrations::class)->names());
        $this->actingAs($user)->get(route('dashboard'))->assertSee('Apply update');

        $this->actingAs($user)->post(route('system.update'))->assertSessionHas('success', 'Update applied.');

        $this->assertTrue(Schema::hasTable('zi_update_probe'));
        $this->assertFalse(app(PendingMigrations::class)->any());

        Schema::dropIfExists('zi_update_probe');
        (new Filesystem)->deleteDirectory($dir);
    }
}
