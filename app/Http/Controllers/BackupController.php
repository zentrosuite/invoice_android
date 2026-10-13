<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Backup\BackupService;
use App\Settings\BackupSettings;
use App\Support\PickedFile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
    ) {}

    public function index(BackupSettings $settings): View
    {
        return view('backup.index', [
            'lastSaved' => $settings->lastSaved(),
            'overdue' => $settings->isOverdue(Invoice::query()->exists()),
            'invoices' => Invoice::query()->count(),
            'customers' => Customer::query()->count(),
        ]);
    }

    /**
     * Write a backup file; the page then offers it to "Save as" or the share sheet.
     */
    public function create(): JsonResponse
    {
        [$path, $name] = $this->backups->create();

        return response()->json(['path' => $path, 'name' => $name, 'mime' => 'application/octet-stream', 'size' => filesize($path)]);
    }

    /**
     * The backup reached somewhere off the phone (Save as completed, or it was shared).
     */
    public function saved(BackupSettings $settings): JsonResponse
    {
        $settings->lastSavedAt = now()->toIso8601String();
        $settings->save();

        return response()->json(['saved' => $settings->lastSavedAt]);
    }

    public function restore(Request $request): JsonResponse
    {
        $path = PickedFile::resolve($request->input('path'));

        if ($path === null) {
            return response()->json(['error' => 'That file could not be opened.'], 422);
        }

        try {
            $this->backups->restore($path);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // The restored data has its own PIN: lock, so the next screen asks for that one.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['restored' => true, 'redirect' => route('login')]);
    }
}
