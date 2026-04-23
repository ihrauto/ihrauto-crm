<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
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

class NegativeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $admin;

    protected User $otherAdmin;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->admin->assignRole('admin');

        $this->otherAdmin = User::factory()->create(['tenant_id' => $this->otherTenant->id, 'role' => 'admin']);
        $this->otherAdmin->assignRole('admin');

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    // ─── Cross-tenant isolation ───

    #[Test]
    public function cannot_update_other_tenant_customer()
    {
        $response = $this->actingAs($this->otherAdmin)
            ->put(route('customers.update', $this->customer), [
                'name' => 'Hacked Name',
                'phone' => '000',
            ]);

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertDatabaseMissing('customers', ['name' => 'Hacked Name']);
    }

    #[Test]
    public function cannot_view_other_tenant_work_order()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->otherAdmin)
            ->get(route('work-orders.show', $wo));

        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    #[Test]
    public function cannot_view_other_tenant_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->otherAdmin)
            ->get(route('invoices.show', $invoice));

        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    // ─── Invoice immutability ───

    #[Test]
    public function cannot_edit_issued_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'issued',
            'locked_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('invoices.update', $invoice), [
                'notes' => 'Trying to modify issued invoice',
            ]);

        $response->assertStatus(403);
    }

    // ─── Payment validation ───

    #[Test]
    public function payment_exceeding_balance_is_rejected()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [['name' => 'Test', 'price' => 100]],
        ]);

        $invoice = DB::transaction(function () use ($wo) {
            $service = app(InvoiceService::class);
            $invoice = $service->createFromWorkOrder($wo);
            $service->issueInvoice($invoice);

            return $invoice->fresh();
        });

        $response = $this->actingAs($this->admin)
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 99999.99,
                'method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function cannot_pay_draft_invoice()
    {
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'total' => 100,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 50,
                'method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── Stock validation ───

    #[Test]
    public function stock_deductions_are_idempotent()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'stock_quantity' => 10,
        ]);

        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'parts_used' => [
                ['product_id' => $product->id, 'name' => $product->name, 'qty' => 2, 'price' => 10],
            ],
        ]);

        $service = app(InvoiceService::class);

        DB::transaction(function () use ($service, $wo) {
            $service->processStockDeductions($wo);
        });

        // Stock should be 8
        $this->assertEquals(8, $product->fresh()->stock_quantity);

        // Call again — should be idempotent
        DB::transaction(function () use ($service, $wo) {
            $service->processStockDeductions($wo);
        });

        // Stock should still be 8, not 6
        $this->assertEquals(8, $product->fresh()->stock_quantity);
    }

    // ─── Invoice creation validation ───

    #[Test]
    public function cannot_create_invoice_with_no_items()
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => null,
            'parts_used' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        DB::transaction(function () use ($wo) {
            app(InvoiceService::class)->createFromWorkOrder($wo);
        });
    }

    // ─── Role-based access ───

    #[Test]
    public function technician_cannot_create_service_bay()
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'technician']);
        $tech->assignRole('technician');

        $response = $this->actingAs($tech)
            ->post(route('work-bays.store'), ['name' => 'Hacked Bay']);

        $response->assertStatus(403);
    }

    #[Test]
    public function receptionist_cannot_access_finance()
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);
        $receptionist->assignRole('receptionist');

        $response = $this->actingAs($receptionist)
            ->get(route('finance.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function receptionist_cannot_record_payment()
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);
        $receptionist->assignRole('receptionist');

        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'issued',
            'total' => 100,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($receptionist)
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 50,
                'method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertStatus(403);
    }
}
