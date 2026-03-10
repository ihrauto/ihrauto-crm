<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;

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

        app(InvoiceService::class)->syncPaymentState($invoice->fresh());
    }
}
