<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\StockService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C-07 regression — StockService guarantees: idempotency, atomicity,
 * no negative stock, proper reversal. These are the same invariants
 * previously baked into InvoiceService; moving them must not regress.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private WorkOrder $workOrder;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($this->tenant);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Stock', 'phone' => '1',
        ]);
        $vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id, 'customer_id' => $customer->id,
            'license_plate' => 'ZH-STK-1', 'make' => 'M', 'model' => 'Q', 'year' => 2020,
        ]);
        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Filter', 'sku' => 'FILT',
            'price' => 10, 'stock_quantity' => 5, 'min_stock_quantity' => 2,
        ]);
        $this->workOrder = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'in_progress',
            'parts_used' => [[
                'product_id' => $this->product->id,
                'name' => 'Filter',
                'qty' => 2,
                'price' => 10,
            ]],
        ]);
    }

    public function test_deducts_stock_and_writes_movement(): void
    {
        DB::transaction(fn () => app(StockService::class)->deductForWorkOrder($this->workOrder));

        $this->assertEquals(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('reference_id', $this->workOrder->id)->count());
    }

    public function test_is_idempotent_on_replay(): void
    {
        $svc = app(StockService::class);

        DB::transaction(fn () => $svc->deductForWorkOrder($this->workOrder));
        DB::transaction(fn () => $svc->deductForWorkOrder($this->workOrder));

        $this->assertEquals(3, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('reference_id', $this->workOrder->id)->count());
    }

    public function test_throws_when_insufficient_and_does_not_mutate(): void
    {
        $this->workOrder->parts_used = [[
            'product_id' => $this->product->id,
            'name' => 'Filter',
            'qty' => 999, // more than stock
            'price' => 10,
        ]];
        $this->workOrder->save();

        $svc = app(StockService::class);

        try {
            DB::transaction(fn () => $svc->deductForWorkOrder($this->workOrder));
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException) {
            // expected
        }

        $this->assertEquals(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(0, StockMovement::where('reference_id', $this->workOrder->id)->count());
    }

    public function test_reversal_restores_stock(): void
    {
        $svc = app(StockService::class);
        DB::transaction(fn () => $svc->deductForWorkOrder($this->workOrder));
        $this->assertEquals(3, $this->product->fresh()->stock_quantity);

        DB::transaction(fn () => $svc->reverseForWorkOrder($this->workOrder));

        $this->assertEquals(5, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('type', 'sale')->count());
        $this->assertSame(1, StockMovement::where('type', 'void_reversal')->count());
    }

    public function test_reversal_is_idempotent(): void
    {
        // Audit-S-7: a retried void task or a second click must not
        // double-increment stock. Lock in the new probe behavior.
        $svc = app(StockService::class);
        DB::transaction(fn () => $svc->deductForWorkOrder($this->workOrder));

        DB::transaction(fn () => $svc->reverseForWorkOrder($this->workOrder));
        $afterFirst = $this->product->fresh()->stock_quantity;

        DB::transaction(fn () => $svc->reverseForWorkOrder($this->workOrder));
        $this->assertSame($afterFirst, $this->product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('type', 'void_reversal')->count());
    }
}
