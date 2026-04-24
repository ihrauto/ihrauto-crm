<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super-admin: destructive tenant archive. Operator must type
 * the literal word DELETE into the `confirmation` field — a tripwire
 * against accidental clicks in an admin UI.
 */
class ArchiveTenantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'in:DELETE'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.in' => 'Please type DELETE to confirm.',
        ];
    }
}
