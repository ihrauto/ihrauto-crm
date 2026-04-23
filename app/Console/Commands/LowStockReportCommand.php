<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        {--dry-run : Show what would be reported without logging}';

    protected $description = 'Scan all tenants for products at or below their reorder threshold.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $totalAlerts = 0;

        Tenant::where('is_active', true)->chunkById(100, function ($tenants) use ($dryRun, &$totalAlerts) {
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
            }
        });

        $this->info("Low-stock scan complete — {$totalAlerts} product(s) at or below threshold.");

        return self::SUCCESS;
    }
}
