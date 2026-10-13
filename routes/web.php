<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\InvoiceSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [RecoveryController::class, 'create'])->name('recover');
    Route::post('forgot-password', [RecoveryController::class, 'store'])->name('recover.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('profile/recovery-code', [ProfileController::class, 'recoveryCode'])->name('profile.recovery');

    // Before the resource, or "search" is read as a customer id.
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::resource('customers', CustomerController::class);

    Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');

    Route::get('settings', [InvoiceSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [InvoiceSettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/profile', [InvoiceSettingsController::class, 'profile'])->name('settings.profile');
});
