<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-13 regression — Product::lowStock scope must exclude products where
 * min_stock_quantity = 0 ("don't alert") and only flag products whose
 * stock has dropped to or below the configured threshold.
 */
class LowStockReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_scope_only_returns_products_at_or_below_threshold(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        // Should fire: stock below threshold.
        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Low',
            'sku' => 'LOW-1',
            'price' => 1,
            'stock_quantity' => 2,
            'min_stock_quantity' => 5,
        ]);
        // Should fire: stock exactly at threshold.
        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Exact',
            'sku' => 'EXACT',
            'price' => 1,
            'stock_quantity' => 5,
            'min_stock_quantity' => 5,
        ]);
        // Should NOT fire: above threshold.
        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Fine',
            'sku' => 'FINE',
            'price' => 1,
            'stock_quantity' => 10,
            'min_stock_quantity' => 5,
        ]);
        // Should NOT fire: threshold=0 means "don't alert".
        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Untracked',
            'sku' => 'UNTRACKED',
            'price' => 1,
            'stock_quantity' => 0,
            'min_stock_quantity' => 0,
        ]);

        $low = Product::lowStock()->pluck('sku')->all();

        $this->assertContains('LOW-1', $low);
        $this->assertContains('EXACT', $low);
        $this->assertNotContains('FINE', $low);
        $this->assertNotContains('UNTRACKED', $low);
    }

    public function test_command_runs_without_side_effects_in_dry_run(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        Product::create([
            'tenant_id' => $tenant->id,
            'name' => 'Low',
            'sku' => 'DRY-1',
            'price' => 1,
            'stock_quantity' => 1,
            'min_stock_quantity' => 5,
        ]);

        $this->artisan('inventory:low-stock-report', ['--dry-run' => true])
            ->assertSuccessful();
    }
}
