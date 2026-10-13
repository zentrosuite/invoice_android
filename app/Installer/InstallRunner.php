<?php

declare(strict_types=1);

namespace App\Installer;

use App\Enums\UsageProfile;
use App\Models\User;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The steps that actually change something: building the schema, then recording the business
 * and the person who owns it.
 *
 * Artisan::call() rather than reaching into the Migrator, so the installer runs exactly the path
 * a developer runs on the command line and cannot drift from it.
 */
final class InstallRunner
{
    public function __construct(
        private readonly Artisan $artisan,
    ) {}

    /**
     * Build the schema (tables and settings).
     *
     * @return array{ok: bool, message: string, output: string, tables: int}
     */
    public function migrate(): array
    {
        try {
            $this->artisan->call('migrate', ['--force' => true, '--no-interaction' => true]);

            return [
                'ok' => true,
                'message' => 'Schema created.',
                'output' => trim($this->artisan->output()),
                'tables' => $this->countTables(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'output' => trim($this->artisan->output()),
                'tables' => $this->countTables(),
            ];
        }
    }

    /**
     * Create the owner and record the business and its usage profile, all or nothing.
     *
     * @param  array{usage_profile: string, business_name: string, phone?: ?string}  $business
     * @param  array{name: string, email: string, password: string}  $account
     * @return array{user: User, recoveryCode: string}
     *
     * @throws Throwable
     */
    public function completeSetup(array $business, array $account): array
    {
        return DB::transaction(function () use ($business, $account): array {
            $user = User::query()->firstWhere('email', $account['email']) ?? new User;
            $user->name = $account['name'];
            $user->email = $account['email'];
            $user->password = $account['password'];
            $user->email_verified_at = now();
            $recoveryCode = $user->issueRecoveryCode();
            $user->save();

            // Single-user application: anything else left over from an earlier attempt goes.
            User::query()->whereKeyNot($user->getKey())->delete();

            $company = app(CompanySettings::class);
            $company->name = trim($business['business_name']);
            $company->phone = $this->nullIfBlank($business['phone'] ?? null);
            $company->email ??= $user->email;
            $company->save();

            $invoice = app(InvoiceSettings::class);
            $invoice->applyProfile(UsageProfile::from($business['usage_profile']));
            $invoice->save();

            return ['user' => $user, 'recoveryCode' => $recoveryCode];
        });
    }

    /**
     * Hand the application back its intended drivers.
     *
     * Database-backed sessions and cache, now that there is a database. The queue stays `sync`:
     * shared hosting runs no worker, and nothing here is slow enough to need one.
     */
    public function restoreProductionDrivers(Installation $installation): void
    {
        // Never from a test: it would rewrite the developer's real .env.
        if (app()->runningUnitTests()) {
            return;
        }

        $installation->environment()->set([
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'sync',
        ]);
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    private function countTables(): int
    {
        try {
            return count(DB::select('SHOW TABLES'));
        } catch (Throwable) {
            return 0;
        }
    }
}
