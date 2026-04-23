<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sent once per tenant per day when at least one product is at or below
 * its reorder threshold. Shape is a digest (all products in one mail)
 * rather than per-product noise.
 *
 * Scalability (BL-5): implements ShouldQueue. The low-stock scheduler
 * otherwise fans out N_tenants × N_admins sync mails in its run slot.
 */
class LowStockDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, \App\Models\Product>  $products
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly Collection $products,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("[{$this->tenant->name}] {$this->products->count()} product(s) need restocking")
            ->greeting('Stock is running low.');

        foreach ($this->products as $p) {
            $mail->line(sprintf(
                '• %s (SKU %s) — %d in stock, threshold %d',
                $p->name,
                $p->sku ?? '—',
                (int) $p->stock_quantity,
                (int) $p->min_stock_quantity,
            ));
        }

        return $mail
            ->action('Open inventory', route('products-services.index'))
            ->line('This summary is sent once a day when any product is at or below its reorder threshold.');
    }
}
