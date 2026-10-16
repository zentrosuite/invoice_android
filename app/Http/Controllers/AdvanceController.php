<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerAdvance;
use App\Models\Invoice;
use App\Services\Invoices\AdvanceService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Advances on the customer page, and adjusting one against an invoice. See AdvanceService.
 */
final class AdvanceController extends Controller
{
    public function __construct(
        private readonly AdvanceService $advances,
    ) {}

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $request->merge(['amount' => preg_replace('/[^0-9.]/', '', (string) $request->input('amount'))]);

        $data = $request->validateWithBag('advance', [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'received_on' => ['required', 'date', 'before_or_equal:' . now()->addDay()->toDateString()],
            'method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Advance])],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.gt' => 'Enter the amount received.',
            'received_on.before_or_equal' => 'The date cannot be in the future.',
        ]);

        $advance = $this->advances->record($customer, $data);

        return redirect()->route('customers.show', $customer)->with('success',
            'Advance of ' . Money::format($advance->amount) . ' recorded. ' . $customer->name . ' now has '
            . Money::format($customer->advanceBalance()) . ' to adjust against invoices.');
    }

    public function destroy(Customer $customer, CustomerAdvance $advance): RedirectResponse
    {
        abort_unless($advance->customer_id === $customer->id, 404);

        try {
            $this->advances->remove($advance);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Advance removed.');
    }

    /** "Adjust advance" on an invoice page. */
    public function apply(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->merge(['amount' => preg_replace('/[^0-9.]/', '', (string) $request->input('amount'))]);
        $available = $this->advances->availableFor($invoice);

        $data = $request->validateWithBag('advance', [
            'amount' => ['required', 'numeric', 'gt:0', 'max:' . $available],
        ], [
            'amount.gt' => 'Enter the amount to adjust.',
            'amount.max' => 'At most ' . Money::format($available, $invoice->currency_symbol) . ' can be adjusted here.',
        ]);

        $payment = $this->advances->apply($invoice, (string) $data['amount']);
        $invoice->refresh();

        return redirect()->route('invoices.show', $invoice)->with('success', $payment === null
            ? 'Nothing was adjusted.'
            : Money::format($payment->amount, $invoice->currency_symbol) . ' adjusted from the advance. Balance '
                . Money::format($invoice->balance(), $invoice->currency_symbol) . '.');
    }
}
