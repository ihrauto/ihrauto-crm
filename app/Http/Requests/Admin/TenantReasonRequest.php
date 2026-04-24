<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super-admin: suspend / activate share the same shape — a single
 * `reason` string gets written to the audit log. This request is
 * deliberately generic so the two controller methods stay symmetric.
 * If suspend and activate later diverge (e.g. activate needs a
 * reactivation date) split them into dedicated requests.
 */
class TenantReasonRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
