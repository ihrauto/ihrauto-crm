<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Auto-issue draft invoices that have sat untouched for longer than
 * each tenant's configured grace period.
 *
 * Tenants opt in via `settings.auto_issue_drafts_after_days` (null or 0
 * disables the behaviour). The command runs daily from the scheduler
 * and is idempotent — once a draft moves to `issued` it drops out of
 * the next run's search.
 *
 * Safety rails:
 *  - Invoices without line items are skipped (InvoiceService would throw).
 *  - Failures per invoice are isolated and logged via report(); the
 *    sweep continues with the next row.
 *  - `--dry-run` mirrors the pattern used by other destructive CLI
 *    commands in this app.
 */
class AutoIssueStaleDraftsCommand extends Command
{
    protected $signature = 'invoices:auto-issue-stale
        {--dry-run : Show what would be issued without changing state}';

    protected $description = 'Auto-issue draft invoices that have been sitting longer than the tenant\'s configured grace period.';

    public function __construct(private readonly InvoiceService $invoiceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processed = 0;
        $issued = 0;

        // Bug review DATA-12: only auto-issue drafts for tenants whose
        // subscription is still active. Auto-issuing an invoice for a
        // locked-out tenant would send dunning emails to their customers
        // with invoices the tenant can't currently manage.
        Tenant::notExpired()->chunkById(100, function ($tenants) use ($dryRun, &$processed, &$issued) {
            foreach ($tenants as $tenant) {
                $days = (int) ($tenant->settings['auto_issue_drafts_after_days'] ?? 0);
                if ($days <= 0) {
                    continue; // opted out
                }

                $cutoff = now()->subDays($days);

                $drafts = Invoice::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', Invoice::STATUS_DRAFT)
                    ->where('updated_at', '<', $cutoff)
                    ->get();

                foreach ($drafts as $draft) {
                    $processed++;

                    if ($dryRun) {
                        $this->line(sprintf(
                            '  [dry-run] tenant=%d invoice=%s age=%dd (threshold=%dd)',
                            $tenant->id,
                            $draft->invoice_number,
                            (int) $draft->updated_at->diffInDays(now(), absolute: true),
                            $days,
                        ));

                        continue;
                    }

                    try {
                        DB::transaction(fn () => $this->invoiceService->issueInvoice($draft));
                        $issued++;
                    } catch (\Throwable $e) {
                        // Don't let one bad row kill the sweep. Common causes:
                        // empty line items, locked-out status, race with a
                        // concurrent manual issue.
                        report($e);
                        $this->warn("skip {$draft->invoice_number}: {$e->getMessage()}");
                    }
                }
            }
        });

        $this->info("Auto-issue sweep complete — reviewed {$processed} stale draft(s), issued {$issued}.");

        return self::SUCCESS;
    }
}
