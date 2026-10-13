<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money received against an invoice, and the status that follows from it.
 *
 * Part paid and Paid are never set by hand: they are what the payments add up to. Recording a
 * payment, removing one, or editing the invoice total all end in reconcile(), so the status and
 * the amount paid can never disagree with the payment list.
 */
final class PaymentService
{
    /**
     * @param  array{amount: string, paid_on: string, method: string, reference?: ?string, note?: ?string}  $data
     */
    public function record(Invoice $invoice, array $data): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data): InvoicePayment {
            $payment = $invoice->payments()->create([
                'amount' => (string) BigDecimal::of($data['amount'])->toScale(2),
                'paid_on' => $data['paid_on'],
                'method' => PaymentMethod::from($data['method']),
                'reference' => $this->text($data['reference'] ?? null),
                'note' => $this->text($data['note'] ?? null),
            ]);

            $this->reconcile($invoice);

            return $payment;
        });
    }

    public function remove(InvoicePayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $invoice = $payment->invoice;
            $payment->delete();
            $this->reconcile($invoice);
        });
    }

    /**
     * "Mark paid": one payment for whatever is still owed.
     */
    public function settle(Invoice $invoice, PaymentMethod $method = PaymentMethod::Other): void
    {
        $balance = BigDecimal::of($invoice->balance());

        if ($balance->isPositive()) {
            $this->record($invoice, [
                'amount' => (string) $balance,
                'paid_on' => now()->toDateString(),
                'method' => $method->value,
                'note' => 'Marked as paid',
            ]);
        } else {
            $this->reconcile($invoice);
        }
    }

    /**
     * "Mark unpaid": forget every payment.
     */
    public function clear(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice->payments()->delete();
            $this->reconcile($invoice);
        });
    }

    /**
     * Bring amount_paid, status and paid_at into line with the payments. Saves.
     */
    public function reconcile(Invoice $invoice): void
    {
        $paid = BigDecimal::of((string) ($invoice->payments()->sum('amount') ?: '0'))->toScale(2);
        $total = BigDecimal::of($invoice->total ?? '0');

        $invoice->amount_paid = (string) $paid;

        if ($invoice->status !== InvoiceStatus::Cancelled) {
            if ($total->isPositive() && $paid->isGreaterThanOrEqualTo($total)) {
                $invoice->status = InvoiceStatus::Paid;
                $last = $invoice->payments()->max('paid_on');
                $invoice->paid_at = $last ? Carbon::parse($last) : now();
            } elseif ($paid->isPositive()) {
                $invoice->status = InvoiceStatus::Partial;
                $invoice->paid_at = null;
            } else {
                if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Partial], true)) {
                    $invoice->status = InvoiceStatus::Sent;
                }
                $invoice->paid_at = null;
            }

            if ($invoice->status === InvoiceStatus::Partial || $invoice->status === InvoiceStatus::Paid) {
                $invoice->sent_at ??= now();
            }
        }

        $invoice->save();
    }

    private function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
