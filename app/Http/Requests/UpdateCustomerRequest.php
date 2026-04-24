<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Support\TenantValidation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    $nameParts = array_filter(explode(' ', trim($value)));
                    if (count($nameParts) < 2) {
                        $fail('Please enter both first name and surname.');
                    }
                },
            ],
            'email' => [
                'nullable',
                'email',
                'max:255',
                // DATA-03: uniqueness on the encrypted `email` column is
                // impossible (random IVs); we enforce through
                // `email_hash` within the tenant, ignoring the current
                // customer row so a user can re-submit their own email.
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }
                    $currentId = $this->route('customer')?->id ?? $this->route('customer');
                    $exists = Customer::where('tenant_id', tenant_id())
                        ->where('email_hash', Customer::lookupEmailHash($value))
                        ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                        ->exists();
                    if ($exists) {
                        $fail('This email address is already registered.');
                    }
                },
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/',
            ],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date|before:today',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please enter a valid phone number.',
            'email.unique' => 'This email address is already registered.',
            'email.email' => 'Please enter a valid email address.',
            'date_of_birth.before' => 'Date of birth must be before today.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_of_birth' => 'date of birth',
            'postal_code' => 'postal code',
        ];
    }
}
