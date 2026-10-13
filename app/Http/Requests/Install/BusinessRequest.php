<?php

declare(strict_types=1);

namespace App\Http\Requests\Install;

use App\Enums\UsageProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BusinessRequest extends FormRequest
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
            'usage_profile' => ['required', Rule::enum(UsageProfile::class)],
            'business_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usage_profile.required' => 'Choose how you will use Zentro Invoices.',
        ];
    }
}
