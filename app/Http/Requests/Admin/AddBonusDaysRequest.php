<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Super-admin: extend a tenant's trial or subscription by a bounded
 * number of days. `reason` is required for the audit log.
 *
 * Authorization lives on the route (`role:super-admin`); rejecting here
 * would mask the route-middleware guard.
 */
class AddBonusDaysRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
