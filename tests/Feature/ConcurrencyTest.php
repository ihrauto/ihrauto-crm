<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->invoiceService = app(InvoiceService::class);
    }

    #[Test]
    public function duplicate_invoice_creation_from_same_work_order_returns_existing()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [['name' => 'Oil Change', 'price' => 80]],
        ]);

        $invoice1 = DB::transaction(fn () => $this->invoiceService->createFromWorkOrder($wo));
        $invoice2 = DB::transaction(fn () => $this->invoiceService->createFromWorkOrder($wo));

        // Both should return the same invoice (idempotent)
        $this->assertEquals($invoice1->id, $invoice2->id);

        // Only one invoice should exist for this work order
        $this->assertEquals(1, Invoice::where('work_order_id', $wo->id)->count());
    }

    #[Test]
    public function invoice_number_generation_produces_unique_numbers()
    {
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = DB::transaction(fn () => $this->invoiceService->generateInvoiceNumber($this->tenant->id));
        }

        // All numbers should be unique
        $this->assertCount(10, array_unique($numbers));
    }

    #[Test]
    public function stock_deduction_only_happens_once_per_work_order()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 20,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'parts_used' => [
                ['product_id' => $product->id, 'name' => $product->name, 'qty' => 5, 'price' => 10],
            ],
        ]);

        // Deduct 3 times
        for ($i = 0; $i < 3; $i++) {
            DB::transaction(fn () => $this->invoiceService->processStockDeductions($wo));
        }

        // Should only deduct once
        $this->assertEquals(15, $product->fresh()->stock_quantity);
        $this->assertEquals(1, \App\Models\StockMovement::where('reference_id', $wo->id)->where('type', 'sale')->count());
    }

    #[Test]
    public function void_invoice_restores_stock_correctly()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 20,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [['name' => 'Brake Service', 'price' => 200]],
            'parts_used' => [
                ['product_id' => $product->id, 'name' => $product->name, 'qty' => 3, 'price' => 15],
            ],
        ]);

        // Create and issue invoice, deduct stock
        $invoice = DB::transaction(function () use ($wo) {
            $this->invoiceService->processStockDeductions($wo);
            $invoice = $this->invoiceService->createFromWorkOrder($wo);
            $this->invoiceService->issueInvoice($invoice);

            return $invoice->fresh();
        });

        $this->assertEquals(17, $product->fresh()->stock_quantity);

        // Void the invoice
        DB::transaction(fn () => $this->invoiceService->voidInvoice($invoice, 'Customer cancelled'));

        // Stock should be restored
        $this->assertEquals(20, $product->fresh()->stock_quantity);
        $this->assertEquals('void', $invoice->fresh()->status);
    }

    #[Test]
    public function payment_idempotency_key_prevents_duplicates()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [['name' => 'Service', 'price' => 100]],
        ]);

        $invoice = DB::transaction(function () use ($wo) {
            $invoice = $this->invoiceService->createFromWorkOrder($wo);
            $this->invoiceService->issueInvoice($invoice);

            return $invoice->fresh();
        });

        $paymentData = [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'method' => 'cash',
            'payment_date' => now()->toDateString(),
            'idempotency_key' => 'unique-key-123',
        ];

        // First payment should succeed
        $this->actingAs($this->admin)
            ->post(route('payments.store'), $paymentData)
            ->assertRedirect();

        // Second attempt with same key should be prevented
        $this->actingAs($this->admin)
            ->post(route('payments.store'), $paymentData)
            ->assertRedirect();

        // Only one payment should exist
        $this->assertEquals(1, \App\Models\Payment::where('idempotency_key', 'unique-key-123')->count());
    }

    #[Test]
    public function issue_invoice_is_idempotent()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [['name' => 'Service', 'price' => 150]],
        ]);

        $invoice = DB::transaction(fn () => $this->invoiceService->createFromWorkOrder($wo));

        // Issue twice
        $issued1 = DB::transaction(fn () => $this->invoiceService->issueInvoice($invoice));
        $issued2 = DB::transaction(fn () => $this->invoiceService->issueInvoice($issued1));

        // Should be the same invoice, issued once
        $this->assertEquals($issued1->id, $issued2->id);
        $this->assertEquals('issued', $issued2->status);
    }
}
