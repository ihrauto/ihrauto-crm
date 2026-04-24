<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editable fields on a draft invoice. The Invoice model and the
 * `prevent_issued_invoice_modification` Postgres trigger enforce the
 * rest of the immutability invariant once the invoice is issued.
 */
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
