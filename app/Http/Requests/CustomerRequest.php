<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CustomerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * One phone number, one customer.
     *
     * The point of saving customers is never typing them twice; a second record for the same
     * number splits their invoice history in two. Compared on the normalised digits, so
     * "+91 98765 43210" and "09876543210" are the same person.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $digits = Customer::normalisePhone($this->input('phone'));

                if ($digits === null || $validator->errors()->has('phone')) {
                    return;
                }

                $current = $this->route('customer');

                $existing = Customer::query()
                    ->where('phone_digits', $digits)
                    ->when($current instanceof Customer, fn ($q) => $q->whereKeyNot($current->getKey()))
                    ->first();

                if ($existing !== null) {
                    $validator->errors()->add('phone', "This number is already saved for {$existing->label()}.");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Use digits, spaces, + and - only.'];
    }

    /**
     * Validated input with blank strings turned into nulls.
     *
     * @return array<string, string|null>
     */
    public function attributesForModel(): array
    {
        return array_map(
            static fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null,
            $this->validated(),
        );
    }
}
