<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super-admin: create or edit an internal note on a tenant.
 * Length cap keeps audit-log payloads bounded.
 */
class TenantNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
