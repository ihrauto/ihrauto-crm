<?php

namespace App\Http\Requests;

use App\Support\TenantValidation;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /*
         * Bug review LOG-03: bound payment_date on both ends.
         *
         * Upper bound (before_or_equal:today): a typo year (e.g. 2062
         * instead of 2026) used to sit happily in `revenue_year` for
         * decades, skewing the monthly revenue chart with a huge outlier.
         * Payments by definition cannot be in the future.
         *
         * Lower bound (after_or_equal: 1-year-ago): retroactive payments
         * more than a year old are almost always a data-entry error or
         * a VAT-period manipulation attempt. The ESTV audit trail makes
         * back-dating across closed quarters a real legal risk, so we
         * refuse at the form layer and force the operator to open a
         * ticket if they genuinely need to back-date.
         */
        return [
            'invoice_id' => ['required', TenantValidation::exists('invoices')],
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|string|in:cash,card,bank_transfer,other',
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subYear()->format('Y-m-d'),
            ],
            'transaction_reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'idempotency_key' => 'nullable|string|max:64',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'An invoice must be selected.',
            'invoice_id.exists' => 'The selected invoice does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least 0.01.',
            'method.required' => 'Payment method is required.',
            'method.in' => 'Invalid payment method selected.',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.date' => 'Payment date must be a valid date.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_date.after_or_equal' => 'Payment date cannot be more than 1 year in the past. Contact support if you need to record an older payment.',
        ];
    }
}
