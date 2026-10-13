<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Settings\InvoiceSettings;

/**
 * Hands out invoice numbers from the series in settings.
 *
 * A number is taken when the invoice is first saved — drafts included — so the owner always
 * knows what to call it. Numbers already in use (typed by hand, or imported) are skipped
 * rather than collided with.
 */
final readonly class InvoiceNumberer
{
    public function __construct(
        private InvoiceSettings $settings,
    ) {}

    /**
     * Take the next free number and advance the series. Call inside the saving transaction.
     */
    public function take(): string
    {
        $next = max(1, $this->settings->nextNumber);

        do {
            $number = $this->format($next);
            $next++;
        } while (Invoice::query()->where('number', $number)->exists());

        $this->settings->nextNumber = $next;
        $this->settings->save();

        return $number;
    }

    public function format(int $sequence): string
    {
        return $this->settings->numberPrefix
            . str_pad((string) $sequence, $this->settings->numberPadding, '0', STR_PAD_LEFT);
    }
}
