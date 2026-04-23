<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\LowStockDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * B-13: daily low-stock report.
 *
 * Walks every active tenant's catalogue and logs products whose
 * stock_quantity has dropped to or below their configured
 * min_stock_quantity. Right now the output is structured log only (so
 * Sentry breadcrumbs and Render logs surface it). When an email or Slack
 * channel is wired, add a notify step around the foreach; the scan logic
 * and debounce surface stays the same.
 */
class LowStockReportCommand extends Command
{
    protected $signature = 'inventory:low-stock-report
        {--dry-run : Show what would be reported without logging or notifying}
        {--notify : Email each tenant\'s admin users with a low-stock digest}';

    protected $description = 'Scan all tenants for products at or below their reorder threshold.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $notify = (bool) $this->option('notify');
        $totalAlerts = 0;
        $notified = 0;

        // Bug review DATA-12: skip expired tenants — they can't take
        // action on the low-stock email anyway until they renew.
        Tenant::notExpired()->chunkById(100, function ($tenants) use (
            $dryRun, $notify, &$totalAlerts, &$notified
        ) {
            foreach ($tenants as $tenant) {
                $products = Product::withoutTenantScope()
                    ->where('tenant_id', $tenant->id)
                    ->lowStock()
                    ->get(['id', 'name', 'sku', 'stock_quantity', 'min_stock_quantity']);

                if ($products->isEmpty()) {
                    continue;
                }

                $totalAlerts += $products->count();

                if ($dryRun) {
                    $this->line("tenant={$tenant->id} ({$tenant->name}): {$products->count()} low-stock");
                    foreach ($products as $p) {
                        $this->line(sprintf(
                            '  - %s [%s] stock=%d threshold=%d',
                            $p->name, $p->sku ?? '-', $p->stock_quantity, $p->min_stock_quantity
                        ));
                    }

                    continue;
                }

                Log::channel(config('logging.default'))->warning('inventory.low_stock', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'count' => $products->count(),
                    'products' => $products->map(fn ($p) => [
                        'id' => $p->id,
                        'sku' => $p->sku,
                        'name' => $p->name,
                        'stock' => (int) $p->stock_quantity,
                        'threshold' => (int) $p->min_stock_quantity,
                    ])->all(),
                ]);

                // Only email when the operator explicitly opts in via --notify,
                // or when the tenant has opted in via settings.low_stock_email.
                $emailOptIn = (bool) ($tenant->settings['low_stock_email'] ?? false);
                if ($notify || $emailOptIn) {
                    $admins = User::withoutGlobalScopes()
                        ->where('tenant_id', $tenant->id)
                        ->where('is_active', true)
                        ->role('admin')
                        ->get();

                    if ($admins->isNotEmpty()) {
                        Notification::send($admins, new LowStockDigestNotification($tenant, $products));
                        $notified += $admins->count();
                    }
                }
            }
        });

        $this->info("Low-stock scan complete — {$totalAlerts} product(s) at or below threshold, {$notified} admin(s) emailed.");

        return self::SUCCESS;
    }
}
