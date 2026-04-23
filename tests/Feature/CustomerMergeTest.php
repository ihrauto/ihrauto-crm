<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\CustomerMergeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint C.13 — customer merge tool.
 */
class CustomerMergeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected CustomerMergeService $merger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->merger = app(CustomerMergeService::class);
    }

    #[Test]
    public function it_transfers_vehicles_from_duplicate_to_primary(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Vehicle::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $primary->id,
        ]);
        Vehicle::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
        ]);

        $this->merger->merge($primary, $duplicate);

        $this->assertEquals(5, Vehicle::where('customer_id', $primary->id)->count());
        $this->assertEquals(0, Vehicle::where('customer_id', $duplicate->id)->count());
    }

    #[Test]
    public function it_transfers_checkins_work_orders_invoices_and_tires(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
        ]);

        $checkin = Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
            'vehicle_id' => $vehicle->id,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
        ]);
        $tire = Tire::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $duplicate->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->merger->merge($primary, $duplicate);

        $this->assertEquals($primary->id, $checkin->fresh()->customer_id);
        $this->assertEquals($primary->id, $workOrder->fresh()->customer_id);
        $this->assertEquals($primary->id, $invoice->fresh()->customer_id);
        $this->assertEquals($primary->id, $tire->fresh()->customer_id);
    }

    #[Test]
    public function it_soft_deletes_the_duplicate_after_merge(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->merger->merge($primary, $duplicate);

        $this->assertSoftDeleted('customers', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('customers', ['id' => $primary->id, 'deleted_at' => null]);
    }

    #[Test]
    public function it_refuses_to_merge_across_tenants(): void
    {
        $tenantB = Tenant::factory()->create();
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('different tenants');

        $this->merger->merge($primary, $duplicate);
    }

    #[Test]
    public function it_refuses_to_merge_a_customer_with_itself(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('itself');

        $this->merger->merge($customer, $customer);
    }

    #[Test]
    public function it_backfills_empty_contact_fields_from_the_duplicate(): void
    {
        // Create with non-null phone to satisfy factory constraints, then clear
        // the fields we want to test as empty.
        $primary = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Primary',
        ]);
        $primary->forceFill([
            'phone' => '',
            'email' => '',
            'address' => '',
        ])->save();

        $duplicate = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Duplicate',
            'phone' => '+41791234567',
            'email' => 'dup@example.com',
            'address' => 'Bahnhofstrasse 1',
        ]);

        $merged = $this->merger->merge($primary, $duplicate);

        $this->assertEquals('+41791234567', $merged->phone);
        $this->assertEquals('dup@example.com', $merged->email);
        $this->assertEquals('Bahnhofstrasse 1', $merged->address);
        // Primary name is preserved
        $this->assertEquals('Primary', $merged->name);
    }

    #[Test]
    public function it_does_not_overwrite_existing_primary_contact_fields(): void
    {
        $primary = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41791111111',
            'email' => 'keep@example.com',
        ]);
        $duplicate = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41799999999',
            'email' => 'discard@example.com',
        ]);

        $merged = $this->merger->merge($primary, $duplicate);

        $this->assertEquals('+41791111111', $merged->phone);
        $this->assertEquals('keep@example.com', $merged->email);
    }

    #[Test]
    public function it_appends_duplicate_notes_to_primary_notes(): void
    {
        $primary = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'notes' => 'Original note',
        ]);
        $duplicate = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'notes' => 'Important historical context',
        ]);

        $merged = $this->merger->merge($primary, $duplicate);

        $this->assertStringContainsString('Original note', $merged->notes);
        $this->assertStringContainsString('Important historical context', $merged->notes);
        $this->assertStringContainsString("Merged from customer #{$duplicate->id}", $merged->notes);
    }

    #[Test]
    public function it_writes_an_audit_log_entry(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Keep']);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Remove']);

        $this->merger->merge($primary, $duplicate);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'customer.merge',
            'model_id' => $primary->id,
        ]);
    }

    #[Test]
    public function http_endpoint_requires_authorization(): void
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $tech->assignRole('technician');

        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($tech)->post(route('customers.merge'), [
            'primary_id' => $primary->id,
            'duplicate_id' => $duplicate->id,
        ]);

        $response->assertForbidden();

        // Both customers should still exist (unchanged)
        $this->assertDatabaseHas('customers', ['id' => $primary->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('customers', ['id' => $duplicate->id, 'deleted_at' => null]);
    }

    #[Test]
    public function http_endpoint_rejects_merging_a_customer_with_itself(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->post(route('customers.merge'), [
            'primary_id' => $customer->id,
            'duplicate_id' => $customer->id,
        ]);

        $response->assertSessionHasErrors('primary_id');
    }
}
