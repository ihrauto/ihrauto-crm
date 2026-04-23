<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B-14: Overdue invoice reminder.
 *
 * Sent to the tenant's billing contact (or the customer directly when the
 * tenant decides to forward it). The reminder is informational — it does
 * not change invoice state and does not record a follow-up automatically.
 *
 * Scalability (BL-5): implements ShouldQueue. The 08:30 daily scheduler
 * can otherwise dispatch 20k sync SMTP calls at 200 tenants × 50 overdue
 * × 2 admins and lock up the scheduler process for 30+ minutes.
 */
class InvoiceOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $daysOverdue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = number_format((float) $this->invoice->total, 2);
        $balance = number_format((float) ($this->invoice->total - $this->invoice->paid_amount), 2);
        $currency = config('crm.currency.code', 'CHF');

        return (new MailMessage)
            ->subject("Overdue invoice #{$this->invoice->invoice_number}")
            ->greeting('Reminder: an invoice is past due.')
            ->line("Invoice #{$this->invoice->invoice_number} for {$currency} {$total} was due "
                ."on {$this->invoice->due_date?->format('d.m.Y')} "
                ."({$this->daysOverdue} day(s) overdue).")
            ->line("Outstanding balance: {$currency} {$balance}.")
            ->action('Review invoice', route('invoices.show', $this->invoice))
            ->line('Please contact the customer or record a payment if this has already been settled.');
    }
}
