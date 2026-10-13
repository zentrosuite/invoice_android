<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\PendingMigrations;
use Illuminate\Contracts\Console\Kernel as Artisan;
use Illuminate\Http\RedirectResponse;
use Throwable;

/**
 * Applying a new version's database changes without a command line.
 */
final class SystemController extends Controller
{
    public function update(Artisan $artisan, PendingMigrations $pending): RedirectResponse
    {
        if (! $pending->any()) {
            return back()->with('info', 'Already up to date.');
        }

        try {
            $artisan->call('migrate', ['--force' => true]);
            $artisan->call('optimize:clear');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'The update could not be applied: ' . $e->getMessage());
        }

        return back()->with('success', 'Update applied.');
    }
}
