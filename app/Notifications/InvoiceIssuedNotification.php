<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when an invoice is issued.
 *
 * Deliberately doesn't attach the PDF — the body includes a signed,
 * expiring link to the print view so the customer can view/print
 * without needing to open an attachment (which many mail clients
 * quarantine). 60-day validity matches typical payment terms.
 *
 * Scalability (BL-5): implements ShouldQueue so the issuing request
 * returns immediately and the mail transport failure can't hang the
 * operator's browser. At 200 tenants a bulk-issue run of 50 invoices
 * generates 50 queued jobs instead of 50 synchronous SMTP round-trips.
 */
class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = config('crm.currency.code', 'CHF');
        $total = number_format((float) $this->invoice->total, 2);
        $due = $this->invoice->due_date?->format('d.m.Y');
        $tenantName = $this->invoice->tenant?->name ?? config('app.name');

        return (new MailMessage)
            ->subject("Invoice #{$this->invoice->invoice_number} from {$tenantName}")
            ->greeting("Hello {$this->invoice->customer?->name},")
            ->line("Your invoice #{$this->invoice->invoice_number} is ready for {$currency} {$total}.")
            ->when($due, fn (MailMessage $mail) => $mail->line("Payment is due on {$due}."))
            ->action('View invoice', $this->invoice->publicPdfUrl())
            ->line('If you have any questions about this invoice, reply to this email or call us.')
            ->salutation("Thank you,\n{$tenantName}");
    }
}
