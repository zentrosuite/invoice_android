<?php

declare(strict_types=1);

use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AutosaveController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceFileController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\Settings\InvoiceSettingsController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

// The launch animation: NativePHP opens the app here (config: nativephp.start_url).
Route::view('launch', 'launch')->name('launch');

// First launch only (EnsureSetUp closes these once a PIN exists).
Route::get('welcome', [WelcomeController::class, 'business'])->name('welcome');
Route::post('welcome', [WelcomeController::class, 'storeBusiness'])->name('welcome.business');
Route::get('welcome/pin', [WelcomeController::class, 'pin'])->name('welcome.pin');
Route::post('welcome/pin', [WelcomeController::class, 'storePin'])->name('welcome.pin.store');
Route::get('welcome/done', [WelcomeController::class, 'done'])->name('welcome.done');
// A new phone or a reinstall: bring a backup back instead of setting up from scratch.
Route::post('welcome/restore', [WelcomeController::class, 'restore'])->name('welcome.restore');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
    Route::get('forgot-pin', [RecoveryController::class, 'create'])->name('recover');
    Route::post('forgot-pin', [RecoveryController::class, 'store'])->name('recover.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('lock', [LoginController::class, 'destroy'])->name('logout');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile/pin', [ProfileController::class, 'pin'])->name('profile.pin');
    Route::post('profile/recovery-code', [ProfileController::class, 'recoveryCode'])->name('profile.recovery');

    // Before the resource, or "search" is read as a customer id.
    Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');
    Route::post('customers/quick', [CustomerController::class, 'quickStore'])->name('customers.quick');
    Route::resource('customers', CustomerController::class);
    Route::post('customers/{customer}/advances', [AdvanceController::class, 'store'])->name('customers.advances.store');
    Route::delete('customers/{customer}/advances/{advance}', [AdvanceController::class, 'destroy'])->name('customers.advances.destroy');

    // Before the resource, or "export" is read as an invoice id.
    Route::post('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    // The builder's background save of unsaved work, and picking it up again after a crash.
    Route::post('invoices/autosave', [AutosaveController::class, 'store'])->name('invoices.autosave');
    Route::get('invoices/autosave/restore', [AutosaveController::class, 'restore'])->name('invoices.autosave.restore');
    Route::delete('invoices/autosave', [AutosaveController::class, 'destroy'])->name('invoices.autosave.destroy');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
    Route::put('invoices/{invoice}/status', [InvoiceController::class, 'status'])->name('invoices.status');
    Route::get('invoices/{invoice}/preview', [InvoiceController::class, 'preview'])->name('invoices.preview');
    // Writes the PDF into the app's storage and returns its path, for the native share/save calls.
    Route::post('invoices/{invoice}/pdf-file', [InvoiceController::class, 'pdfFile'])->name('invoices.pdf-file');
    Route::post('invoices/{invoice}/print-file', [InvoiceController::class, 'printFile'])->name('invoices.print-file');
    Route::post('invoices/{invoice}/shared', [InvoiceController::class, 'shared'])->name('invoices.shared');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/advance', [AdvanceController::class, 'apply'])->name('invoices.advance');
    Route::delete('invoices/{invoice}/payments/{payment}', [PaymentController::class, 'destroy'])->name('invoices.payments.destroy');

    Route::get('company', [CompanyProfileController::class, 'edit'])->name('company.edit');
    Route::put('company', [CompanyProfileController::class, 'update'])->name('company.update');

    Route::get('settings', [InvoiceSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [InvoiceSettingsController::class, 'update'])->name('settings.update');
    Route::put('settings/profile', [InvoiceSettingsController::class, 'profile'])->name('settings.profile');

    Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('backup', [BackupController::class, 'create'])->name('backup.create');
    Route::post('backup/saved', [BackupController::class, 'saved'])->name('backup.saved');
    Route::post('backup/restore', [BackupController::class, 'restore'])->name('backup.restore');

    // A file the system picker copied into the app's cache, streamed back so the page can read it
    // (a photo becomes a PNG in the page before upload — the phone's PHP cannot open JPEG).
    Route::get('device-file', DeviceFileController::class)->name('device-file');
    Route::get('media/{file}', MediaController::class)->name('media');
});
