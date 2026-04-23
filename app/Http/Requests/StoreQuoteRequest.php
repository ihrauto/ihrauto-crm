<?php

namespace App\Http\Requests;

use App\Support\TenantValidation;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy-checked in the controller
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', TenantValidation::exists('customers')],
            'vehicle_id' => ['nullable', TenantValidation::exists('vehicles')],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'A quote needs at least one line item.',
            'items.min' => 'A quote needs at least one line item.',
            'items.*.description.required' => 'Each line item needs a description.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
        ];
    }
}
