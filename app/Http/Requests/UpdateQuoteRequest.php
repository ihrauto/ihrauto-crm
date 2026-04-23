<?php

namespace App\Http\Requests;

use App\Support\TenantValidation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Updates are only accepted on quotes in editable statuses (draft/sent).
 * The controller enforces that; this request only validates shape.
 */
class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', TenantValidation::exists('customers')],
            'vehicle_id' => ['nullable', TenantValidation::exists('vehicles')],
            'issue_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'string', 'in:draft,sent,accepted,rejected'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
