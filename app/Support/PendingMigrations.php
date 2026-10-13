<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

/**
 * Database changes shipped in a new version that have not been applied yet.
 *
 * Shared hosting has no shell to run `php artisan migrate`, so after uploading a new version
 * the owner applies them from a button in the app instead (SystemController::update).
 */
final class PendingMigrations
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    /**
     * @return list<string>
     */
    public function names(): array
    {
        try {
            if (! $this->migrator->repositoryExists()) {
                return [];
            }

            $paths = array_merge([database_path('migrations')], $this->migrator->paths(), (array) config('settings.migrations_paths', []));
            $files = $this->migrator->getMigrationFiles($paths);
            $ran = $this->migrator->getRepository()->getRan();

            return array_values(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return [];
        }
    }

    public function any(): bool
    {
        return $this->names() !== [];
    }
}
