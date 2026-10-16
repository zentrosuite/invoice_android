<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Autosave;
use App\Models\Invoice;
use App\Services\Invoices\AutosaveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The invoice builder's background save, and the "continue / discard" choice that follows a
 * closed or crashed app. See AutosaveStore.
 */
final class AutosaveController extends Controller
{
    public function __construct(
        private readonly AutosaveStore $store,
    ) {}

    /** Called by the builder every few seconds while there is unsaved work. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'form' => ['required', 'string', 'max:' . AutosaveStore::MAX_BYTES],
        ]);

        if (! $this->validKey($data['key'])) {
            return response()->json(['error' => 'Unknown invoice.'], 422);
        }

        $autosave = $this->store->put($data['key'], $data['form']);

        return response()->json(['saved_at' => $autosave->updated_at?->toIso8601String()]);
    }

    /** Open the builder again with the saved fields, through the same old() path a failed save uses. */
    public function restore(Request $request): RedirectResponse
    {
        $key = (string) $request->query('key', '');
        $autosave = $this->validKey($key) ? $this->store->find($key) : null;

        if ($autosave === null) {
            return redirect()->route('invoices.index')->with('error', 'That unsaved invoice is no longer there.');
        }

        $target = $autosave->invoiceId() === null
            ? route('invoices.create')
            : route('invoices.edit', $autosave->invoiceId());

        return redirect($target)
            ->withInput($autosave->input())
            ->with('autosave_restored', $autosave->updated_at?->toIso8601String());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $key = (string) $request->input('key', '');
        if (Autosave::isValidKey($key)) {
            $this->store->forget($key);
        }

        return back()->with('success', 'Unsaved invoice discarded.');
    }

    private function validKey(string $key): bool
    {
        if (! Autosave::isValidKey($key)) {
            return false;
        }

        $id = (new Autosave(['key' => $key]))->invoiceId();

        return $id === null || Invoice::query()->whereKey($id)->exists();
    }
}
