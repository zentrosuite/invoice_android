<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\Money;

/**
 * The WhatsApp message that goes with an invoice's PDF.
 *
 * On the phone there is no website, so no customer link: the PDF itself is sent, and this is
 * its caption. {link} in a template carried over from the website is simply dropped.
 */
final readonly class InvoiceSharing
{
    public function __construct(
        private InvoiceSettings $settings,
        private CompanySettings $company,
    ) {}

    /**
     * The message with placeholders filled in.
     */
    public function message(Invoice $invoice): string
    {
        $due = $invoice->status === InvoiceStatus::Paid
            ? 'paid — thank you'
            : ($invoice->due_date ? 'due by ' . $invoice->due_date->format('d/m/Y') : ($invoice->payment_terms ?: 'due on receipt'));

        $text = strtr($this->settings->whatsappMessage, [
            '{customer}' => $invoice->customer_name ?: ($invoice->customer_business ?: 'there'),
            '{number}' => $invoice->number,
            '{company}' => $this->company->name,
            // What they are being asked for: the balance while money is owed, else the total.
            '{amount}' => Money::short(
                $invoice->status->isPayable() ? $invoice->balance() : $invoice->total,
                $invoice->currency_symbol,
            ),
            '{due}' => $due,
            '{link}' => '',
        ]);

        // Tidy what a removed {link} leaves behind ("View, download or pay: " on its own line).
        $text = preg_replace('/^.*:\s*$/m', '', $text) ?? $text;

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * The invoice has gone to the customer. A draft becomes unpaid: they have it now.
     */
    public function markShared(Invoice $invoice): void
    {
        $invoice->shared_at = now();

        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->status = InvoiceStatus::Sent;
            $invoice->sent_at ??= now();
        }

        $invoice->save();
    }
}
