<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests to verify tenant isolation - users should never access other tenants' data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $userA;

    protected User $userB;

    protected Customer $customerA;

    protected Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two separate tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        // Create users for each tenant
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'admin',
        ]);
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'admin',
        ]);

        // Create customers for each tenant
        $this->customerA = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // =========================================
    // CUSTOMER ISOLATION TESTS
    // =========================================

    /** @test */
    public function user_cannot_view_other_tenant_customer()
    {
        $this->actingAs($this->userA);

        $response = $this->get(route('customers.show', $this->customerB));

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_update_other_tenant_customer()
    {
        $this->actingAs($this->userA);

        $response = $this->put(route('customers.update', $this->customerB), [
            'name' => 'Hacked Name',
            'email' => 'hacked@example.com',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_delete_other_tenant_customer()
    {
        $this->actingAs($this->userA);

        $response = $this->delete(route('customers.destroy', $this->customerB));

        $response->assertStatus(404);

        // Verify customer still exists
        $this->assertDatabaseHas('customers', ['id' => $this->customerB->id]);
    }

    // =========================================
    // VEHICLE ISOLATION TESTS (Query Scope)
    // =========================================

    /** @test */
    public function vehicle_query_only_returns_own_tenant_records()
    {
        Vehicle::factory()->count(2)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);
        Vehicle::factory()->count(4)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $this->actingAs($this->userA);

        $vehicles = Vehicle::all();

        // Should only see tenant A vehicles
        $this->assertCount(2, $vehicles);

        foreach ($vehicles as $vehicle) {
            $this->assertEquals($this->tenantA->id, $vehicle->tenant_id);
        }
    }

    // =========================================
    // INVOICE ISOLATION TESTS
    // =========================================

    /** @test */
    public function user_cannot_view_other_tenant_invoice()
    {
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $this->actingAs($this->userA);

        $response = $this->get(route('invoices.show', $invoiceB));

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_update_other_tenant_invoice()
    {
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($this->userA);

        $response = $this->put(route('invoices.update', $invoiceB), [
            'notes' => 'Hacked notes',
        ]);

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_delete_other_tenant_invoice()
    {
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($this->userA);

        $response = $this->delete(route('invoices.destroy', $invoiceB));

        $response->assertStatus(404);

        $this->assertDatabaseHas('invoices', ['id' => $invoiceB->id]);
    }

    // =========================================
    // WORK ORDER ISOLATION TESTS
    // =========================================

    /** @test */
    public function user_cannot_view_other_tenant_work_order()
    {
        $vehicleB = Vehicle::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'vehicle_id' => $vehicleB->id,
        ]);

        $this->actingAs($this->userA);

        $response = $this->get(route('work-orders.show', $workOrderB));

        $response->assertStatus(404);
    }

    /** @test */
    public function user_cannot_update_other_tenant_work_order()
    {
        $vehicleB = Vehicle::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $workOrderB = WorkOrder::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'vehicle_id' => $vehicleB->id,
        ]);

        $this->actingAs($this->userA);

        $response = $this->put(route('work-orders.update', $workOrderB), [
            'customer_issues' => 'Hacked issues',
        ]);

        $response->assertStatus(404);
    }

    // =========================================
    // PRODUCT ISOLATION TESTS
    // =========================================

    /** @test */
    public function product_query_only_returns_own_tenant_records()
    {
        Product::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Product::factory()->count(5)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        $products = Product::all();

        // Should only see tenant A products
        $this->assertCount(3, $products);

        foreach ($products as $product) {
            $this->assertEquals($this->tenantA->id, $product->tenant_id);
        }
    }

    // =========================================
    // TIRE ISOLATION TESTS
    // =========================================

    /** @test */
    public function user_cannot_view_other_tenant_tire()
    {
        $vehicleB = Vehicle::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $tireB = Tire::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'vehicle_id' => $vehicleB->id,
        ]);

        $this->actingAs($this->userA);

        // Use correct route name
        $response = $this->get(route('tires-hotel.show', $tireB));

        $response->assertStatus(404);
    }

    // =========================================
    // QUERY SCOPE ISOLATION TESTS
    // =========================================

    /** @test */
    public function customer_query_only_returns_own_tenant_records()
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenantA->id]);
        Customer::factory()->count(5)->create(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->userA);

        $customers = Customer::all();

        // Should only see tenant A customers (3 + 1 from setUp = 4)
        $this->assertCount(4, $customers);

        foreach ($customers as $customer) {
            $this->assertEquals($this->tenantA->id, $customer->tenant_id);
        }
    }

    /** @test */
    public function invoice_query_only_returns_own_tenant_records()
    {
        Invoice::factory()->count(2)->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);
        Invoice::factory()->count(4)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $this->actingAs($this->userA);

        $invoices = Invoice::all();

        $this->assertCount(2, $invoices);

        foreach ($invoices as $invoice) {
            $this->assertEquals($this->tenantA->id, $invoice->tenant_id);
        }
    }

    // =========================================
    // POLICY AUTHORIZATION TESTS
    // =========================================

    /** @test */
    public function policy_denies_view_for_other_tenant_invoice()
    {
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        $this->actingAs($this->userA);

        $this->assertFalse($this->userA->can('view', $invoiceB));
    }

    /** @test */
    public function policy_denies_update_for_other_tenant_invoice()
    {
        $invoiceB = Invoice::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->actingAs($this->userA);

        $this->assertFalse($this->userA->can('update', $invoiceB));
    }

    /** @test */
    public function policy_allows_view_for_same_tenant_invoice()
    {
        $invoiceA = Invoice::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $this->customerA->id,
        ]);

        $this->actingAs($this->userA);

        $this->assertTrue($this->userA->can('view', $invoiceA));
    }
}
