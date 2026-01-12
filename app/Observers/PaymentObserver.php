<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Payment;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        $this->updateInvoiceBalance($payment->invoice);
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        // Only update if amount changed or invoice changed
        if ($payment->isDirty('amount') || $payment->isDirty('invoice_id')) {
            $this->updateInvoiceBalance($payment->invoice);

            // If invoice ID changed, update the OLD invoice too
            if ($payment->isDirty('invoice_id')) {
                $oldInvoiceId = $payment->getOriginal('invoice_id');
                if ($oldInvoiceId) {
                    $oldInvoice = Invoice::find($oldInvoiceId);
                    if ($oldInvoice) {
                        $this->updateInvoiceBalance($oldInvoice);
                    }
                }
            }
        }
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        $this->updateInvoiceBalance($payment->invoice);
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        $this->updateInvoiceBalance($payment->invoice);
    }

    /**
     * Recalculate invoice paid amount and status.
     */
    private function updateInvoiceBalance(?Invoice $invoice): void
    {
        if (! $invoice) {
            return;
        }

        $totalPaid = $invoice->payments()->sum('amount');

        $invoice->paid_amount = round($totalPaid, 2);

        // Update Status based on payment logic
        if ($invoice->paid_amount >= $invoice->total) {
            $invoice->status = 'paid';
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        } elseif ($invoice->status !== 'draft') {
            $invoice->status = 'unpaid';
        }

        // $invoice->save();
        // Debugging: Force update via DB to see if Eloquent is blocking
        \Illuminate\Support\Facades\DB::table('invoices')
            ->where('id', $invoice->id)
            ->update([
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
            ]);
    }
}
