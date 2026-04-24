<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk-issue action on the Finance → Drafts tab. Hard cap at 200 invoices
 * per click matches the UI's bulk-select affordance and protects the
 * queue from a runaway batch.
 */
class BulkIssueInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => ['required', 'array', 'min:1', 'max:200'],
            'invoice_ids.*' => ['integer'],
        ];
    }
}
