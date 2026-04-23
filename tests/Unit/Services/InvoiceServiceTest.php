<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $service;

    protected User $user;

    protected Tenant $tenant;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InvoiceService;

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function it_generates_unique_invoice_numbers()
    {
        $number1 = $this->service->generateInvoiceNumber();

        // Create an invoice to increment the count
        Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $number2 = $this->service->generateInvoiceNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith(config('crm.invoice.prefix'), $number1);
    }

    #[Test]
    public function it_creates_invoice_from_work_order()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [
                ['name' => 'Oil Change', 'price' => 50, 'completed' => true],
            ],
        ]);

        DB::transaction(function () use ($workOrder) {
            $invoice = $this->service->createFromWorkOrder($workOrder);

            $this->assertInstanceOf(Invoice::class, $invoice);
            $this->assertEquals($workOrder->id, $invoice->work_order_id);
            $this->assertEquals($this->customer->id, $invoice->customer_id);
            $this->assertNotNull($invoice->invoice_number);
        });
    }

    #[Test]
    public function it_returns_existing_invoice_if_one_already_exists()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        // Create an invoice for this work order
        $existingInvoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'customer_id' => $this->customer->id,
        ]);

        // Service should return the existing invoice (idempotent behavior)
        $returnedInvoice = $this->service->createFromWorkOrder($workOrder);

        $this->assertEquals($existingInvoice->id, $returnedInvoice->id);
    }

    #[Test]
    public function it_creates_invoice_items_from_service_tasks()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [
                ['name' => 'Oil Change', 'price' => 50, 'completed' => true],
                ['name' => 'Tire Rotation', 'price' => 30, 'completed' => true],
            ],
        ]);

        DB::transaction(function () use ($workOrder) {
            $invoice = $this->service->createFromWorkOrder($workOrder);

            $this->assertEquals(2, $invoice->items->count());
        });
    }

    #[Test]
    public function it_creates_invoice_items_from_parts_used()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => 'Oil Filter', 'qty' => 1, 'price' => 25],
                ['name' => 'Air Filter', 'qty' => 1, 'price' => 15],
            ],
        ]);

        DB::transaction(function () use ($workOrder) {
            $invoice = $this->service->createFromWorkOrder($workOrder);

            $this->assertEquals(2, $invoice->items->count());
        });
    }

    #[Test]
    public function it_processes_stock_deductions_for_parts()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 10,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => 'Oil Filter', 'qty' => 2, 'price' => 25, 'product_id' => $product->id],
            ],
        ]);

        $this->service->processStockDeductions($workOrder);

        $product->refresh();
        $this->assertEquals(8, $product->stock_quantity);

        // Check stock movement was logged
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => -2,
            'type' => 'sale',
            'reference_id' => $workOrder->id,
        ]);
    }

    #[Test]
    public function it_rejects_invoice_with_no_tasks_or_parts()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'customer_issues' => 'General Maintenance',
            'service_tasks' => null,
            'parts_used' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no service tasks or parts recorded');

        DB::transaction(function () use ($workOrder) {
            $this->service->createFromWorkOrder($workOrder);
        });
    }

    #[Test]
    public function it_uses_config_tax_rate()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [
                ['name' => 'Test Service', 'price' => 100],
            ],
        ]);

        DB::transaction(function () use ($workOrder) {
            $invoice = $this->service->createFromWorkOrder($workOrder);

            $item = $invoice->items->first();
            $this->assertEquals(config('crm.tax_rate'), $item->tax_rate);
        });
    }

    #[Test]
    public function it_throws_insufficient_stock_exception_when_quantity_exceeds_available()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => $product->name, 'qty' => 10, 'price' => 25, 'product_id' => $product->id],
            ],
        ]);

        $this->expectException(\App\Exceptions\InsufficientStockException::class);

        DB::transaction(function () use ($workOrder) {
            $this->service->processStockDeductions($workOrder);
        });
    }

    #[Test]
    public function insufficient_stock_does_not_partially_deduct_other_products()
    {
        // Product A has enough stock, Product B does not.
        // If A is processed before B, we must NOT deduct A when B fails.
        $productA = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Product A',
            'stock_quantity' => 10,
        ]);
        $productB = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Product B',
            'stock_quantity' => 2,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => 'Product A', 'qty' => 3, 'price' => 10, 'product_id' => $productA->id],
                ['name' => 'Product B', 'qty' => 5, 'price' => 20, 'product_id' => $productB->id], // insufficient
            ],
        ]);

        try {
            DB::transaction(function () use ($workOrder) {
                $this->service->processStockDeductions($workOrder);
            });
            $this->fail('Expected InsufficientStockException was not thrown');
        } catch (\App\Exceptions\InsufficientStockException $e) {
            // expected
        }

        // Neither product should have been deducted (atomic)
        $productA->refresh();
        $productB->refresh();
        $this->assertEquals(10, $productA->stock_quantity, 'Product A was deducted despite exception');
        $this->assertEquals(2, $productB->stock_quantity, 'Product B was modified despite exception');

        // No stock movements should exist
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => WorkOrder::class,
            'reference_id' => $workOrder->id,
        ]);
    }

    #[Test]
    public function it_allows_deduction_when_stock_is_exactly_equal_to_quantity()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 5,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => $product->name, 'qty' => 5, 'price' => 25, 'product_id' => $product->id],
            ],
        ]);

        DB::transaction(function () use ($workOrder) {
            $this->service->processStockDeductions($workOrder);
        });

        $product->refresh();
        $this->assertEquals(0, $product->stock_quantity);
    }

    #[Test]
    public function stock_deductions_are_idempotent_when_called_twice()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 10,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => $product->name, 'qty' => 3, 'price' => 10, 'product_id' => $product->id],
            ],
        ]);

        // First call: deducts stock
        DB::transaction(function () use ($workOrder) {
            $this->service->processStockDeductions($workOrder);
        });

        $product->refresh();
        $this->assertEquals(7, $product->stock_quantity);

        // Second call: must be a no-op
        DB::transaction(function () use ($workOrder) {
            $this->service->processStockDeductions($workOrder);
        });

        $product->refresh();
        $this->assertEquals(7, $product->stock_quantity, 'Stock was double-deducted');

        // Only one movement should exist
        $this->assertEquals(
            1,
            \App\Models\StockMovement::where('reference_type', WorkOrder::class)
                ->where('reference_id', $workOrder->id)
                ->count()
        );
    }
}
