<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Inclusion;
use App\Enums\InvoiceTemplate;
use App\Enums\PricingMode;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class InvoiceRequest extends FormRequest
{
    private const MONEY_MAX = 9999999999.99;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invoice = $this->route('invoice');

        return [
            'number' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\/\-_#]+$/',
                Rule::unique('invoices', 'number')->ignore($invoice instanceof Invoice ? $invoice->id : null),
            ],
            'status' => ['nullable', Rule::in(['draft', 'sent'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'walkin_name' => ['nullable', 'string', 'max:150'],
            'walkin_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'pricing_mode' => ['required', Rule::enum(PricingMode::class)],
            'template' => ['required', Rule::enum(InvoiceTemplate::class)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'payment_terms' => ['nullable', 'string', 'max:80'],
            'show_quantity' => ['nullable', 'boolean'],
            'package_total' => ['nullable', 'required_if:pricing_mode,package', 'numeric', 'min:0', 'max:' . self::MONEY_MAX],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:' . self::MONEY_MAX],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Part of the customer's advance to adjust against this invoice. Capped on save at
            // what is actually available (AdvanceService::apply).
            'apply_advance' => ['nullable', 'numeric', 'min:0', 'max:' . self::MONEY_MAX],

            'callout' => ['nullable', 'array'],
            'callout.title' => ['nullable', 'string', 'max:120'],
            'callout.text' => ['nullable', 'string', 'max:255'],
            'callout.amount' => ['nullable', 'numeric', 'min:0', 'max:' . self::MONEY_MAX],
            'callout.suffix' => ['nullable', 'string', 'max:20'],
            'callout.meta' => ['nullable', 'string', 'max:80'],

            'terms' => ['nullable', 'string', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'footer_note' => ['nullable', 'string', 'max:120'],

            'items' => ['required', 'array', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'string', 'max:1000'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'items.*.rate' => ['nullable', 'numeric', 'min:0', 'max:' . self::MONEY_MAX],
            'items.*.inclusion' => ['nullable', Rule::enum(Inclusion::class)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $described = array_filter(
                    (array) $this->input('items', []),
                    static fn ($item) => is_array($item) && trim((string) ($item['description'] ?? '')) !== '',
                );

                if ($described === []) {
                    $validator->errors()->add('items', 'Add at least one line with a description.');
                }

                if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                    $validator->errors()->add('discount_value', 'A percentage discount cannot be more than 100.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'Use letters, numbers and / - _ # only.',
            'number.unique' => 'Another invoice already has this number.',
            'package_total.required_if' => 'Enter the package price.',
            'due_date.after_or_equal' => 'The due date cannot be before the invoice date.',
            'walkin_phone.regex' => 'Use digits, spaces, + and - only.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items.*.description' => 'description',
            'items.*.quantity' => 'quantity',
            'items.*.rate' => 'rate',
            'callout.title' => 'highlight title',
            'callout.amount' => 'highlight amount',
        ];
    }

    /**
     * Amounts arrive formatted the way people type them — "1,25,000", "₹ 899" — so the grouping
     * and symbols are stripped before the numeric rules look at them.
     */
    protected function prepareForValidation(): void
    {
        $clean = static fn ($value) => is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;

        $merge = [];

        foreach (['package_total', 'discount_value', 'tax_percent', 'apply_advance'] as $field) {
            if ($this->has($field)) {
                $merge[$field] = $clean($this->input($field));
            }
        }

        if (is_array($this->input('items'))) {
            $merge['items'] = array_map(static function ($item) use ($clean) {
                if (! is_array($item)) {
                    return $item;
                }
                foreach (['quantity', 'rate'] as $field) {
                    if (array_key_exists($field, $item)) {
                        $item[$field] = $clean($item[$field]);
                    }
                }

                return $item;
            }, $this->input('items'));
        }

        if (is_array($this->input('callout')) && array_key_exists('amount', $this->input('callout'))) {
            $merge['callout'] = array_merge($this->input('callout'), ['amount' => $clean($this->input('callout')['amount'])]);
        }

        $this->merge($merge);
    }
}
