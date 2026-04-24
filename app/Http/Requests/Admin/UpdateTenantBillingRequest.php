<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Super-admin: flip a tenant onto a paid plan and set the renewal date.
 * `reason` is required for the audit log; renewal_date is optional and
 * defaults to +1 month downstream.
 */
class UpdateTenantBillingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::in(Tenant::ALL_PLANS)],
            'renewal_date' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
