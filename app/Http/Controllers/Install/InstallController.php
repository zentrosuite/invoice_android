<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Enums\UsageProfile;
use App\Http\Requests\Install\AccountRequest;
use App\Http\Requests\Install\BusinessRequest;
use App\Http\Requests\Install\DatabaseRequest;
use App\Installer\DatabaseProvisioner;
use App\Installer\Installation;
use App\Installer\InstallRunner;
use App\Installer\RequirementsCheck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The setup wizard: requirements → database → tables → business → owner account → done.
 *
 * Nothing about the business is written until the last form is submitted. Steps 4 and 5 are
 * held in the session and committed together, so abandoning the wizard half way leaves no
 * half-configured company behind.
 */
final class InstallController extends \Illuminate\Routing\Controller
{
    private const SESSION_DB = 'install.database';

    private const SESSION_MIGRATED = 'install.migrated';

    private const SESSION_BUSINESS = 'install.business';

    private const COOKIE_COMPLETE = 'zi_setup_complete';

    public function welcome(): RedirectResponse
    {
        return redirect()->route('install.requirements');
    }

    public function requirements(Request $request): View
    {
        $installation = Installation::make();

        // The first web request ever served is the first time anything knows the real base URL,
        // subdirectory and all. Only replaces the shipped placeholder, never a deliberate value.
        if (! app()->runningUnitTests()) {
            $installation->captureAppUrl($request->getSchemeAndHttpHost() . $request->getBaseUrl());
        }

        return view('install.requirements', [
            'step' => 1,
            'checks' => RequirementsCheck::make()->run(),
            'degraded' => $installation->isDegraded(),
            'fixCommand' => $installation->permissionCommand(),
            'detectedUrl' => $installation->environment()->get('APP_URL'),
        ]);
    }

    public function database(Request $request, DatabaseProvisioner $provisioner): View
    {
        $env = Installation::make()->environment();
        $saved = (array) $request->session()->get(self::SESSION_DB, []);
        $database = (string) ($saved['database'] ?? $env->get('DB_DATABASE', 'zentro_invoices'));

        return view('install.database', [
            'step' => 2,
            'config' => [
                'host' => $saved['host'] ?? $env->get('DB_HOST', '127.0.0.1'),
                'port' => $saved['port'] ?? $env->get('DB_PORT', '3306'),
                'database' => $database,
                'username' => $saved['username'] ?? $env->get('DB_USERNAME', ''),
                'password' => $saved['password'] ?? '',
            ],
            'createStatement' => $provisioner->createStatement($database),
        ]);
    }

    /**
     * Test the credentials, create the database if asked to, and write .env.
     */
    public function storeDatabase(DatabaseRequest $request, DatabaseProvisioner $provisioner): RedirectResponse
    {
        $config = $request->config();

        $server = $provisioner->testServer($config);

        if (! $server['ok']) {
            return back()->withInput()->with('error', $server['message']);
        }

        if (! $provisioner->databaseExists($config)) {
            if (! $request->boolean('create_database')) {
                return back()->withInput()->with('error', sprintf(
                    'There is no database called "%s" on that server. Tick "create it for me", '
                    . 'or create it in your hosting panel and try again.',
                    $config['database'],
                ));
            }

            $created = $provisioner->createDatabase($config);

            if (! $created['ok']) {
                return back()->withInput()->with('error', $created['message']);
            }
        }

        $verified = $provisioner->verify($config);

        if (! $verified['ok']) {
            return back()->withInput()->with('error', $verified['message']);
        }

        Installation::make()->environment()->set([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
        ]);

        // Also in the session: the next screen counts tables before the framework has been
        // restarted with the new configuration.
        $request->session()->put(self::SESSION_DB, $config);
        $request->session()->forget(self::SESSION_MIGRATED);

        return redirect()->route('install.migrate')->with(
            'success',
            $verified['tables'] > 0
                ? sprintf('Connected. Note that this database already contains %d tables.', $verified['tables'])
                : 'Connected, and the database is empty and ready.',
        );
    }

    public function migrate(Request $request, DatabaseProvisioner $provisioner): View
    {
        $config = (array) $request->session()->get(self::SESSION_DB, []);

        return view('install.migrate', [
            'step' => 3,
            'database' => $config['database'] ?? config('database.connections.mysql.database'),
            'existingTables' => $config === [] ? 0 : $provisioner->tableCount($config),
            'done' => (bool) $request->session()->get(self::SESSION_MIGRATED, false),
        ]);
    }

    public function runMigrations(Request $request, InstallRunner $runner): RedirectResponse
    {
        $result = $runner->migrate();

        if (! $result['ok']) {
            return back()->with('error', $result['message'])->with('output', $result['output']);
        }

        $request->session()->put(self::SESSION_MIGRATED, true);

        return redirect()->route('install.business')->with(
            'success',
            sprintf('Database ready — %d tables created.', $result['tables']),
        );
    }

    public function business(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get(self::SESSION_MIGRATED, false)) {
            return redirect()->route('install.migrate')
                ->with('warning', 'Create the database tables first — the next steps write into them.');
        }

        return view('install.business', [
            'step' => 4,
            'profiles' => UsageProfile::cases(),
            'saved' => (array) $request->session()->get(self::SESSION_BUSINESS, []),
        ]);
    }

    public function storeBusiness(BusinessRequest $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_BUSINESS, $request->validated());

        return redirect()->route('install.account');
    }

    public function account(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION_BUSINESS)) {
            return redirect()->route('install.business');
        }

        return view('install.account', ['step' => 5]);
    }

    public function storeAccount(AccountRequest $request, InstallRunner $runner): RedirectResponse
    {
        $business = (array) $request->session()->get(self::SESSION_BUSINESS, []);

        if ($business === []) {
            return redirect()->route('install.business');
        }

        $installation = Installation::make();

        try {
            /** @var array{usage_profile: string, business_name: string, phone?: ?string} $business */
            $result = $runner->completeSetup($business, $request->validated());
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Could not finish setup: ' . $e->getMessage());
        }

        $installation->markInstalled();
        $runner->restoreProductionDrivers($installation);

        Auth::logout();
        $request->session()->forget([self::SESSION_DB, self::SESSION_MIGRATED, self::SESSION_BUSINESS]);

        /*
         * A cookie, not a session flash. restoreProductionDrivers() has just moved sessions from
         * files to the database, so the next request starts a brand-new session and anything
         * flashed into the old one is gone — including the recovery code, which is shown exactly
         * once. Laravel encrypts cookies, and this one lives ten minutes.
         */
        return redirect()->route('install.complete')->withCookie(cookie(
            self::COOKIE_COMPLETE,
            json_encode(['email' => $result['user']->email, 'code' => $result['recoveryCode']]),
            10,
        ));
    }

    public function complete(Request $request): Response
    {
        $data = json_decode((string) $request->cookie(self::COOKIE_COMPLETE), true);

        return response()->view('install.complete', [
            'step' => 6,
            'email' => is_array($data) ? ($data['email'] ?? null) : null,
            'recoveryCode' => is_array($data) ? ($data['code'] ?? null) : null,
        ])->withoutCookie(self::COOKIE_COMPLETE);
    }
}
