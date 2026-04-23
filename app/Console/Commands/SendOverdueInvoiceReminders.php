<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * B-14: scan every active tenant for invoices that are past their due
 * date, not paid/void, and haven't been nudged in the last 3 days. Email
 * a reminder to the tenant's admin users.
 *
 * Designed to be run daily from the scheduler. Uses
 * BelongsToTenant::withoutTenantScope() so the command can walk all
 * tenants in a single pass instead of one artisan run per tenant.
 */
class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-overdue-reminders
        {--dry-run : Report what would be sent without actually sending}
        {--min-days-between-nudges=3 : Debounce per-invoice so we don\'t spam}';

    protected $description = 'Email a reminder for each overdue, unpaid invoice on every active tenant.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $debounceDays = max(1, (int) $this->option('min-days-between-nudges'));

        $processed = 0;
        $sent = 0;

        Tenant::where('is_active', true)->chunkById(100, function ($tenants) use (
            $dryRun, $debounceDays, &$processed, &$sent
        ) {
            foreach ($tenants as $tenant) {
                $query = Invoice::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->whereDate('due_date', '<', now())
                    ->whereNotIn('status', [
                        Invoice::STATUS_DRAFT,
                        Invoice::STATUS_PAID,
                        Invoice::STATUS_VOID,
                    ])
                    ->whereRaw('total - paid_amount > 0')
                    ->where(function ($q) use ($debounceDays) {
                        $q->whereNull('last_reminder_sent_at')
                            ->orWhere('last_reminder_sent_at', '<', now()->subDays($debounceDays));
                    });

                $query->chunkById(50, function ($invoices) use ($tenant, $dryRun, &$processed, &$sent) {
                    $admins = User::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('is_active', true)
                        ->role('admin')
                        ->get();

                    if ($admins->isEmpty()) {
                        return; // nobody to notify in this tenant
                    }

                    foreach ($invoices as $invoice) {
                        $processed++;
                        $daysOverdue = (int) now()->diffInDays($invoice->due_date, absolute: true);

                        if ($dryRun) {
                            $this->line(sprintf(
                                '  [dry-run] tenant=%d invoice=%s overdue=%dd -> %d admin(s)',
                                $tenant->id,
                                $invoice->invoice_number,
                                $daysOverdue,
                                $admins->count(),
                            ));

                            continue;
                        }

                        Notification::send(
                            $admins,
                            new InvoiceOverdueNotification($invoice, $daysOverdue)
                        );

                        // Debounce: avoid re-emailing the same invoice every day.
                        $invoice->forceFill(['last_reminder_sent_at' => now()])->saveQuietly();
                        $sent++;
                    }
                });
            }
        });

        $this->info("Scanned {$processed} overdue invoice(s); sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
