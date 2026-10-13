<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicInvoiceController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\InvoiceSettingsController;
use App\Http\Controllers\SystemController;
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
    Route::post('customers/quick', [CustomerController::class, 'quickStore'])->name('customers.quick');
    Route::resource('customers', CustomerController::class);

    // Before the resource, or "export" is read as an invoice id.
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::put('invoices/{invoice}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/shared', [InvoiceController::class, 'shared'])->name('invoices.shared');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::delete('invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy'])->name('invoices.payments.destroy');
    Route::post('invoices/{invoice}/reset-link', [InvoiceController::class, 'resetLink'])->name('invoices.reset-link');

    Route::post('system/update', [SystemController::class, 'update'])->name('system.update');

    Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');

    Route::get('settings', [InvoiceSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [InvoiceSettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/profile', [InvoiceSettingsController::class, 'profile'])->name('settings.profile');
});

/*
| The customer's link. No login — the 40-character token is the permission. Throttled so the
| token space cannot be walked, and PDFs (the expensive part) more tightly than pages.
*/
Route::middleware('throttle:60,1')->prefix('i')->name('public.')->group(function (): void {
    Route::get('{token}', [PublicInvoiceController::class, 'show'])->name('invoice');
    Route::get('{token}/document', [PublicInvoiceController::class, 'document'])->name('document');
    Route::get('{token}/pdf', [PublicInvoiceController::class, 'pdf'])->middleware('throttle:20,1')->name('pdf');
});
