<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Autosave;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps the invoice builder's unsaved work, one row per builder, until the invoice is saved or
 * the owner discards it.
 */
final class AutosaveStore
{
    /** A form far bigger than any real invoice (200 lines of text) is not stored. */
    public const MAX_BYTES = 512 * 1024;

    public function put(string $key, string $payload): Autosave
    {
        $autosave = Autosave::query()->firstOrNew(['key' => $key]);
        $autosave->payload = $payload;
        $autosave->label = $this->label($autosave->input());
        $autosave->exists && ! $autosave->isDirty() ? $autosave->touch() : $autosave->save();

        return $autosave;
    }

    public function find(string $key): ?Autosave
    {
        return Autosave::query()->where('key', $key)->first();
    }

    public function forget(string $key): void
    {
        Autosave::query()->where('key', $key)->delete();
    }

    /**
     * Unfinished work worth offering, newest first. Edits of invoices that no longer exist are
     * dropped on the way.
     *
     * @return Collection<int, Autosave>
     */
    public function unfinished(): Collection
    {
        return Autosave::query()->latest('updated_at')->get()->filter(function (Autosave $a): bool {
            if ($a->invoiceId() === null || Invoice::query()->whereKey($a->invoiceId())->exists()) {
                return true;
            }
            $a->delete();

            return false;
        })->values();
    }

    /**
     * "Zentro Builders · 3 lines · ₹2,400" — who it is for, how much was typed.
     *
     * @param  array<string, mixed>  $input
     */
    private function label(array $input): string
    {
        $who = null;
        if (! empty($input['customer_id']) && ($customer = Customer::withTrashed()->find((int) $input['customer_id'])) !== null) {
            $who = $customer->label();
        } elseif (trim((string) ($input['walkin_name'] ?? '')) !== '') {
            $who = trim((string) $input['walkin_name']);
        }

        $lines = collect(is_array($input['items'] ?? null) ? $input['items'] : [])
            ->filter(fn ($i) => is_array($i) && trim((string) ($i['description'] ?? '')) !== '');

        $parts = [$who ?? 'No customer yet'];
        if ($lines->isNotEmpty()) {
            $parts[] = $lines->count() === 1 ? '1 line' : $lines->count() . ' lines';
        }
        if (($input['pricing_mode'] ?? '') === 'package' && is_numeric($input['package_total'] ?? null) && (float) $input['package_total'] > 0) {
            $parts[] = Money::short((string) $input['package_total']);
        }

        return Str::limit(implode(' · ', $parts), 195);
    }
}
