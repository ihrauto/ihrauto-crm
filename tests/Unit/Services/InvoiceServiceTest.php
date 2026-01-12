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

    /** @test */
    public function it_generates_unique_invoice_numbers()
    {
        $number1 = $this->service->generateInvoiceNumber();

        // Create an invoice to increment the count
        Invoice::factory()->create(['tenant_id' => $this->tenant->id]);

        $number2 = $this->service->generateInvoiceNumber();

        $this->assertNotEquals($number1, $number2);
        $this->assertStringStartsWith(config('crm.invoice.prefix'), $number1);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_creates_fallback_item_when_no_tasks_or_parts()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'customer_issues' => 'General Maintenance',
            'service_tasks' => null,
            'parts_used' => null,
        ]);

        DB::transaction(function () use ($workOrder) {
            $invoice = $this->service->createFromWorkOrder($workOrder);

            $this->assertEquals(1, $invoice->items->count());
            $this->assertEquals('General Maintenance', $invoice->items->first()->description);
        });
    }

    /** @test */
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
}
