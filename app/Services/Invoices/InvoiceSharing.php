<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * The customer's private link to an invoice, and the WhatsApp message that carries it.
 *
 * WhatsApp's click-to-chat (wa.me) opens a chat with a number and pre-filled text, but cannot
 * attach a file. So the message carries a link to a page where the customer can see the
 * invoice, download the PDF and pay by UPI. The link's token is random and unguessable, and
 * can be reset, which kills the old link.
 */
final readonly class InvoiceSharing
{
    public const TOKEN_LENGTH = 10;

    public function __construct(
        private InvoiceSettings $settings,
        private CompanySettings $company,
    ) {}

    /**
     * Give the invoice a share token if it has none. Saves.
     */
    public function ensureToken(Invoice $invoice): string
    {
        if ($invoice->share_token === null) {
            $invoice->share_token = self::newToken();
            $invoice->saveQuietly();
        }

        return $invoice->share_token;
    }

    /**
     * A new token; anyone holding the old link loses access.
     */
    public function resetToken(Invoice $invoice): void
    {
        $invoice->share_token = self::newToken();
        $invoice->saveQuietly();
    }

    public function publicUrl(Invoice $invoice): string
    {
        return route('public.invoice', $this->ensureToken($invoice));
    }

    /**
     * The message with placeholders filled in.
     */
    public function message(Invoice $invoice): string
    {
        $due = $invoice->status === InvoiceStatus::Paid
            ? 'paid — thank you'
            : ($invoice->due_date ? 'due by ' . $invoice->due_date->format('d/m/Y') : ($invoice->payment_terms ?: 'due on receipt'));

        return strtr($this->settings->whatsappMessage, [
            '{customer}' => $invoice->customer_name ?: ($invoice->customer_business ?: 'there'),
            '{number}' => $invoice->number,
            '{company}' => $this->company->name,
            // What they are being asked for: the balance while money is owed, else the total.
            '{amount}' => Money::short(
                $invoice->status->isPayable() ? $invoice->balance() : $invoice->total,
                $invoice->currency_symbol,
            ),
            '{due}' => $due,
            '{link}' => $this->publicUrl($invoice),
        ]);
    }

    /**
     * wa.me link straight to the customer's chat, or to WhatsApp's contact picker when the
     * invoice has no usable phone number.
     */
    public function whatsappUrl(Invoice $invoice): string
    {
        $text = rawurlencode($this->message($invoice));
        $number = $invoice->whatsappNumber();

        return $number !== null
            ? "https://wa.me/{$number}?text={$text}"
            : "https://wa.me/?text={$text}";
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

    /**
     * Ten letters and digits: short enough to read in a WhatsApp message, and at 62^10 (~8×10^17)
     * combinations against a limit of 60 tries a minute, not something anyone can guess.
     */
    private static function newToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (Invoice::query()->where('share_token', $token)->exists());

        return $token;
    }
}
