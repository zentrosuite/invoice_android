<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\Inclusion;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Customer;
use App\Models\Invoice;
use App\Settings\InvoiceSettings;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates, copies and moves invoices between statuses.
 *
 * The one place an invoice is written, so the rules hold everywhere: customer details are
 * copied onto the invoice, totals come from InvoiceCalculator and nowhere else, and lines are
 * replaced as a set inside the same transaction as the header.
 */
final readonly class InvoiceService
{
    public function __construct(
        private InvoiceCalculator $calculator,
        private InvoiceNumberer $numberer,
        private InvoiceSettings $settings,
    ) {}

    /**
     * A new, unsaved invoice carrying today's defaults, for the builder to start from.
     */
    public function blank(?Customer $customer = null): Invoice
    {
        $template = $this->settings->templateEnum();
        $today = now()->startOfDay();

        $invoice = new Invoice([
            'number' => $this->numberer->format($this->settings->nextNumber),
            'status' => InvoiceStatus::Draft,
            'pricing_mode' => $template->isPackage() ? PricingMode::Package : PricingMode::Itemised,
            'template' => $template->value,
            'invoice_date' => $today,
            'due_date' => $this->settings->dueDays === null ? null : $today->copy()->addDays($this->settings->dueDays),
            'payment_terms' => $this->settings->paymentTerms,
            'item_label' => $this->settings->itemLabel,
            'show_quantity' => $this->settings->showQuantity,
            'quantity_label' => $this->settings->quantityLabel,
            'rate_label' => $this->settings->rateLabel,
            'currency_symbol' => $this->settings->currencySymbol,
            'tax_percent' => $this->settings->defaultTaxPercent,
            'terms' => $this->settings->defaultTerms,
            'notes' => $this->settings->defaultNotes,
            'footer_note' => $this->settings->footerNote,
        ]);

        if ($customer !== null) {
            $invoice->customer_id = $customer->id;
            $this->copyCustomer($invoice, $customer);
        }

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $data  Validated InvoiceRequest data.
     */
    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $invoice = $this->blank();

            $number = trim((string) ($data['number'] ?? ''));
            $invoice->number = $number !== '' && $number !== $invoice->number
                ? $number
                : $this->numberer->take();

            $this->fill($invoice, $data);
            $this->applyStatus($invoice, InvoiceStatus::from($data['status'] ?? InvoiceStatus::Draft->value));
            $invoice->save();
            $this->writeItems($invoice, $data);

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated InvoiceRequest data.
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $number = trim((string) ($data['number'] ?? ''));

            if ($number !== '') {
                $invoice->number = $number;
            }

            $this->fill($invoice, $data);

            if (isset($data['status'])) {
                $this->applyStatus($invoice, InvoiceStatus::from($data['status']));
            }

            $invoice->save();
            $this->writeItems($invoice, $data);

            return $invoice;
        });
    }

    /**
     * A fresh draft with the same customer and lines, today's date and the next number.
     */
    public function duplicate(Invoice $source): Invoice
    {
        return DB::transaction(function () use ($source): Invoice {
            // Never the share link: a copy is a different invoice with its own secret.
            $copy = $source->replicate(['number', 'status', 'sent_at', 'paid_at', 'share_token', 'shared_at', 'viewed_at']);
            $copy->number = $this->numberer->take();
            $copy->status = InvoiceStatus::Draft;
            $copy->invoice_date = now()->startOfDay();
            $copy->due_date = $this->settings->dueDays === null ? null : now()->startOfDay()->addDays($this->settings->dueDays);

            // Refresh the customer copy: duplicating last month's bill should pick up their new address.
            if ($source->customer_id !== null && ($customer = Customer::query()->find($source->customer_id)) !== null) {
                $this->copyCustomer($copy, $customer);
            }

            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create($item->replicate(['invoice_id'])->getAttributes());
            }

            return $copy;
        });
    }

    public function changeStatus(Invoice $invoice, InvoiceStatus $status): void
    {
        $this->applyStatus($invoice, $status);
        $invoice->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(Invoice $invoice, array $data): void
    {
        $mode = PricingMode::from($data['pricing_mode']);

        $invoice->pricing_mode = $mode;
        $invoice->template = $data['template'];
        $invoice->invoice_date = $data['invoice_date'];
        $invoice->due_date = $data['due_date'] ?? null;
        $invoice->payment_terms = $this->text($data['payment_terms'] ?? null);
        $invoice->show_quantity = (bool) ($data['show_quantity'] ?? false);

        $customerId = $data['customer_id'] ?? null;
        $customer = $customerId === null ? null : Customer::withTrashed()->find($customerId);

        if ($customer !== null) {
            $invoice->customer_id = $customer->id;
            $this->copyCustomer($invoice, $customer);
        } else {
            // Walk-in: whatever was typed, possibly nothing.
            $invoice->customer_id = null;
            $invoice->customer_name = $this->text($data['walkin_name'] ?? null);
            $invoice->customer_phone = $this->text($data['walkin_phone'] ?? null);
            $invoice->customer_business = null;
            $invoice->customer_email = null;
            $invoice->customer_address = null;
            $invoice->customer_tax_id = null;
        }

        $invoice->package_total = $mode === PricingMode::Package ? ($data['package_total'] ?? '0') : null;
        $invoice->discount_type = in_array($data['discount_type'] ?? null, ['amount', 'percent'], true) ? $data['discount_type'] : null;
        $invoice->discount_value = $this->text($data['discount_value'] ?? null);
        $invoice->tax_percent = $this->text($data['tax_percent'] ?? null);

        $callout = array_map(fn ($v) => $this->text(is_string($v) ? $v : null), (array) ($data['callout'] ?? []));
        $invoice->callout = ($callout['title'] ?? null) !== null
            ? array_filter($callout, static fn ($v) => $v !== null)
            : null;

        $invoice->terms = $this->text($data['terms'] ?? null);
        $invoice->notes = $this->text($data['notes'] ?? null);
        $invoice->footer_note = $this->text($data['footer_note'] ?? null);

        $totals = $this->calculator->calculate(
            $mode,
            $this->itemInputs($data),
            $invoice->show_quantity,
            $invoice->package_total,
            $invoice->discount_type,
            $invoice->discount_value,
            $invoice->tax_percent,
        );

        $invoice->subtotal = $totals['subtotal'];
        $invoice->discount_amount = $totals['discount_amount'];
        $invoice->tax_amount = $totals['tax_amount'];
        $invoice->total = $totals['total'];
    }

    /**
     * Replace the lines with the submitted set, amounts from the calculator.
     *
     * @param  array<string, mixed>  $data
     */
    private function writeItems(Invoice $invoice, array $data): void
    {
        $inputs = $this->itemInputs($data);
        $totals = $this->calculator->calculate(
            $invoice->pricing_mode,
            $inputs,
            $invoice->show_quantity,
            $invoice->package_total,
            null,
            null,
            null,
        );

        $invoice->items()->delete();

        foreach ($inputs as $index => $input) {
            $invoice->items()->create([
                'position' => $index + 1,
                'description' => trim((string) $input['description']),
                'details' => $this->text($input['details'] ?? null),
                'quantity' => $totals['lines'][$index]['quantity'],
                'rate' => $totals['lines'][$index]['rate'],
                'amount' => $totals['lines'][$index]['amount'],
                'inclusion' => $invoice->isPackage()
                    ? (Inclusion::tryFrom((string) ($input['inclusion'] ?? '')) ?? Inclusion::Included)
                    : null,
            ]);
        }

        $invoice->unsetRelation('items');
    }

    /**
     * Submitted lines with blank rows dropped, in order.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function itemInputs(array $data): array
    {
        return array_values(array_filter(
            (array) ($data['items'] ?? []),
            static fn ($item): bool => is_array($item) && trim((string) ($item['description'] ?? '')) !== '',
        ));
    }

    private function applyStatus(Invoice $invoice, InvoiceStatus $status): void
    {
        $invoice->status = $status;

        if ($status === InvoiceStatus::Sent || $status === InvoiceStatus::Paid) {
            $invoice->sent_at ??= now();
        }

        $invoice->paid_at = $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null;
    }

    private function copyCustomer(Invoice $invoice, Customer $customer): void
    {
        $invoice->customer_name = $customer->name;
        $invoice->customer_business = $customer->business_name;
        $invoice->customer_phone = $customer->phone;
        $invoice->customer_email = $customer->email;
        $invoice->customer_address = implode("\n", $customer->addressLines()) ?: null;
        $invoice->customer_tax_id = $customer->tax_id;
    }

    private function text(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
