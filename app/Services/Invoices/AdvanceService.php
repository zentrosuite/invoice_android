<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Advances: money a customer pays before the invoice, kept as a credit on the customer and
 * adjusted against their invoices later.
 *
 * An adjustment is recorded through PaymentService as a payment with method "advance", so the
 * invoice's status, amount paid and payment list stay in step exactly as for any payment.
 * Removing that payment (or deleting the invoice) puts the money back on the customer.
 */
final readonly class AdvanceService
{
    public function __construct(
        private PaymentService $payments,
    ) {}

    /**
     * @param  array{amount: string, received_on: string, method: string, reference?: ?string, note?: ?string}  $data
     */
    public function record(Customer $customer, array $data): CustomerAdvance
    {
        return $customer->advances()->create([
            'amount' => (string) BigDecimal::of($data['amount'])->toScale(2),
            'received_on' => $data['received_on'],
            'method' => PaymentMethod::from($data['method']),
            'reference' => self::text($data['reference'] ?? null),
            'note' => self::text($data['note'] ?? null),
        ]);
    }

    /**
     * Only the part of an advance not yet adjusted can be removed: deleting money that already
     * paid an invoice would leave that invoice paid by nothing.
     *
     * @throws RuntimeException
     */
    public function remove(CustomerAdvance $advance): void
    {
        DB::transaction(function () use ($advance): void {
            $customer = $advance->customer;
            $after = BigDecimal::of($this->rawBalance($customer))->minus((string) $advance->amount);

            if ($after->isNegative()) {
                throw new RuntimeException('Part of this advance is already adjusted against invoices. Remove those adjustments from the invoices first.');
            }

            $advance->delete();
        });
    }

    /**
     * The most that can be adjusted against this invoice now: the smaller of the customer's
     * advance and what the invoice still owes. Zero when it cannot take an advance at all.
     */
    public function availableFor(Invoice $invoice): string
    {
        $customer = $invoice->customer_id ? Customer::withTrashed()->find($invoice->customer_id) : null;

        if ($customer === null || $invoice->status === InvoiceStatus::Cancelled) {
            return '0.00';
        }

        return (string) BigDecimal::min(BigDecimal::of($customer->advanceBalance()), BigDecimal::of($invoice->balance()))->toScale(2);
    }

    /**
     * Adjust up to $amount of the customer's advance against the invoice. The amount is capped
     * at what is available (see availableFor), so a stale screen can never overdraw it.
     * Returns the payment, or null when nothing could be adjusted.
     */
    public function apply(Invoice $invoice, string $amount, ?string $paidOn = null): ?InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $amount, $paidOn): ?InvoicePayment {
            $amount = BigDecimal::min(BigDecimal::of($amount)->toScale(2), BigDecimal::of($this->availableFor($invoice)));

            if (! $amount->isPositive()) {
                return null;
            }

            return $this->payments->record($invoice, [
                'amount' => (string) $amount,
                'paid_on' => $paidOn ?? now()->toDateString(),
                'method' => PaymentMethod::Advance->value,
                'note' => 'Adjusted from advance',
                'customer_id' => $invoice->customer_id,
            ]);
        });
    }

    /** Σ advances − Σ adjustments, allowed to be negative (advanceBalance() clamps at zero). */
    private function rawBalance(Customer $customer): string
    {
        $received = (string) ($customer->advances()->sum('amount') ?: '0');
        $used = (string) ($customer->advanceAdjustments()->sum('amount') ?: '0');

        return (string) BigDecimal::of($received)->minus($used)->toScale(2);
    }

    private static function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
