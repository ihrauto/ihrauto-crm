<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Void an issued invoice. A non-trivial reason is required for the audit
 * trail; capped at 500 chars so an operator pasting a stack-trace into
 * the form doesn't bloat the invoice row.
 */
class VoidInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
