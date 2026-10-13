<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

final class CompanyProfileRequest extends FormRequest
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
            'tagline' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{6,30}$/'],
            'website' => ['nullable', 'url', 'max:150'],
            'address_line1' => ['nullable', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:80'],
            'state' => ['nullable', 'string', 'max:80'],
            'pincode' => ['nullable', 'string', 'max:12'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'upi_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9.\-_]{2,}@[a-z][a-z0-9]{1,}$/'],
            'upi_payee_name' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_signature' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Use digits, spaces, + and - only.',
            'upi_id.regex' => 'A UPI ID looks like name@bank, e.g. 9876543210@ybl.',
            'logo.max' => 'The logo must be under 4 MB.',
            'signature.max' => 'The signature must be under 4 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['upi_id' => 'UPI ID', 'tax_id' => 'GSTIN / tax ID', 'address_line1' => 'address'];
    }

    /**
     * Blank strings become null before validation, so an emptied field clears the setting.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('upi_id'))) {
            // UPI IDs are case-insensitive and often pasted with stray spaces.
            $this->merge(['upi_id' => strtolower(preg_replace('/\s+/', '', $this->input('upi_id')) ?? '')]);
        }

        if (is_string($this->input('website')) && $this->input('website') !== ''
            && ! preg_match('#^https?://#i', $this->input('website'))) {
            $this->merge(['website' => 'https://' . $this->input('website')]);
        }
    }
}
