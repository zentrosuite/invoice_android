<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Invoices\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            return back()->with('error', 'This invoice is cancelled. Reopen it before recording a payment.');
        }

        $request->merge(['amount' => preg_replace('/[^0-9.]/', '', (string) $request->input('amount'))]);
        $balance = $invoice->balance();

        $data = $request->validateWithBag('payment', [
            'amount' => ['required', 'numeric', 'gt:0', 'max:' . $balance],
            'paid_on' => ['required', 'date', 'before_or_equal:' . now()->addDay()->toDateString()],
            // Advance is not a way of paying; it is adjusted through AdvanceController.
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Advance])],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.max' => 'That is more than the balance of ' . Money::format($balance, $invoice->currency_symbol) . '.',
            'amount.gt' => 'Enter the amount received.',
            'paid_on.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        $this->payments->record($invoice, $data);
        $invoice->refresh();

        return redirect()->route('invoices.show', $invoice)->with('success', $invoice->status === InvoiceStatus::Paid
            ? "{$invoice->number} is now fully paid."
            : 'Payment recorded. Balance ' . Money::format($invoice->balance(), $invoice->currency_symbol) . '.');
    }

    public function destroy(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        abort_unless($payment->invoice_id === $invoice->id, 404);

        $this->payments->remove($payment);

        return redirect()->route('invoices.show', $invoice)->with('success', 'Payment removed.');
    }
}
