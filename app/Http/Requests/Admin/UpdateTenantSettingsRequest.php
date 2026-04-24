<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The management → settings form carries the full tenant profile plus
 * financial defaults plus module toggles. Authorization is handled by
 * the `perform-admin-actions` Gate inside the controller.
 */
class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Company profile
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'uid_number' => ['nullable', 'string', 'max:50'],
            'vat_registered' => ['boolean'],
            'vat_number' => ['nullable', 'required_if:vat_registered,1', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'iban' => ['nullable', 'string', 'max:50'],
            'account_holder' => ['nullable', 'string', 'max:100'],
            'invoice_email' => ['nullable', 'email', 'max:255'],
            'invoice_phone' => ['nullable', 'string', 'max:50'],

            // Financial defaults
            'currency' => ['required', 'string', 'size:3'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/i'],
            'default_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            // 0 / null disables auto-issue. Capped at 60 days so a
            // forgotten year-old draft can't silently auto-issue.
            'auto_issue_drafts_after_days' => ['nullable', 'integer', 'min:0', 'max:60'],

            // Notifications
            'low_stock_email' => ['nullable', 'boolean'],

            // Module toggles
            'module_tire_hotel' => ['nullable', 'string'],
            'module_checkin' => ['nullable', 'string'],
        ];
    }
}
