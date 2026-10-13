<?php

declare(strict_types=1);

use App\Http\Controllers\Install\InstallController;
use Illuminate\Support\Facades\Route;

/*
| The setup wizard. Unauthenticated by necessity: these routes create the first account.
| RedirectIfNotInstalled closes them the moment the install marker exists.
*/

Route::middleware('web')->prefix('install')->name('install.')->group(function (): void {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('requirements', [InstallController::class, 'requirements'])->name('requirements');
    Route::get('database', [InstallController::class, 'database'])->name('database');
    Route::post('database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('migrate', [InstallController::class, 'migrate'])->name('migrate');
    Route::post('migrate', [InstallController::class, 'runMigrations'])->name('migrate.run');
    Route::get('business', [InstallController::class, 'business'])->name('business');
    Route::post('business', [InstallController::class, 'storeBusiness'])->name('business.store');
    Route::get('account', [InstallController::class, 'account'])->name('account');
    Route::post('account', [InstallController::class, 'storeAccount'])->name('account.store');
    Route::get('complete', [InstallController::class, 'complete'])->name('complete');
});
