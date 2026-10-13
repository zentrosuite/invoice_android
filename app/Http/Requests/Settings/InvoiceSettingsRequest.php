<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\InvoiceTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_label' => ['required', 'string', 'max:40'],
            'show_quantity' => ['nullable', 'boolean'],
            'quantity_label' => ['required', 'string', 'max:20'],
            'rate_label' => ['required', 'string', 'max:20'],
            'template' => ['required', Rule::enum(InvoiceTemplate::class)],
            'number_prefix' => ['nullable', 'string', 'max:12', 'regex:/^[A-Za-z0-9\/\-_#]*$/'],
            'next_number' => ['required', 'integer', 'min:1', 'max:99999999'],
            'number_padding' => ['required', 'integer', 'min:1', 'max:8'],
            'default_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'payment_terms' => ['nullable', 'string', 'max:80'],
            'default_terms' => ['nullable', 'string', 'max:3000'],
            'default_notes' => ['nullable', 'string', 'max:1000'],
            'footer_note' => ['nullable', 'string', 'max:120'],
            // {link} is required: without it the customer gets a message with nothing to open.
            'whatsapp_message' => ['required', 'string', 'max:1000', 'regex:/\{link\}/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number_prefix.regex' => 'Use letters, numbers and / - _ # only.',
            'whatsapp_message.regex' => 'Keep {link} in the message — it is how the customer opens the invoice.',
        ];
    }
}
