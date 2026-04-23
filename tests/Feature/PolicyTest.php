<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $admin;

    protected User $technician;

    protected User $receptionist;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->admin->assignRole('admin');

        $this->technician = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'technician']);
        $this->technician->assignRole('technician');

        $this->receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);
        $this->receptionist->assignRole('receptionist');

        $this->otherUser = User::factory()->create(['tenant_id' => $this->otherTenant->id, 'role' => 'admin']);
        $this->otherUser->assignRole('admin');
    }

    // ─── ProductPolicy ───

    #[Test]
    public function product_policy_allows_same_tenant_user()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($this->admin->can('view', $product));
        $this->assertTrue($this->admin->can('update', $product));
        $this->assertTrue($this->admin->can('delete', $product));
    }

    #[Test]
    public function product_policy_denies_cross_tenant_access()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->otherUser->can('view', $product));
        $this->assertFalse($this->otherUser->can('update', $product));
        $this->assertFalse($this->otherUser->can('delete', $product));
    }

    #[Test]
    public function product_policy_denies_force_delete()
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->admin->can('forceDelete', $product));
    }

    // ─── ServicePolicy ───

    #[Test]
    public function service_policy_allows_same_tenant_user()
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oil Change',
            'price' => 50,
        ]);

        $this->assertTrue($this->admin->can('update', $service));
        $this->assertTrue($this->admin->can('delete', $service));
    }

    #[Test]
    public function service_policy_denies_cross_tenant_access()
    {
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oil Change',
            'price' => 50,
        ]);

        $this->assertFalse($this->otherUser->can('update', $service));
        $this->assertFalse($this->otherUser->can('delete', $service));
    }

    // ─── WorkOrderPolicy ───

    #[Test]
    public function work_order_policy_allows_same_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'created',
        ]);

        $this->assertTrue($this->admin->can('view', $wo));
        $this->assertTrue($this->admin->can('update', $wo));
    }

    #[Test]
    public function work_order_policy_denies_update_on_completed()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
        ]);

        $this->assertFalse($this->admin->can('update', $wo));
    }

    #[Test]
    public function work_order_policy_denies_cross_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertFalse($this->otherUser->can('view', $wo));
        $this->assertFalse($this->otherUser->can('update', $wo));
    }

    // ─── InvoicePolicy ───

    #[Test]
    public function invoice_policy_allows_update_on_draft()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->assertTrue($this->admin->can('update', $invoice));
    }

    #[Test]
    public function invoice_policy_denies_update_on_issued()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'issued',
            'locked_at' => now(),
        ]);

        $this->assertFalse($this->admin->can('update', $invoice));
    }

    #[Test]
    public function invoice_policy_denies_cross_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'draft',
        ]);

        $this->assertFalse($this->otherUser->can('view', $invoice));
        $this->assertFalse($this->otherUser->can('update', $invoice));
        $this->assertFalse($this->otherUser->can('delete', $invoice));
    }

    // ─── AppointmentPolicy ───

    #[Test]
    public function appointment_policy_allows_same_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Test',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'type' => 'service',
            'status' => 'scheduled',
        ]);

        $this->assertTrue($this->admin->can('view', $appointment));
        $this->assertTrue($this->admin->can('update', $appointment));
    }

    #[Test]
    public function appointment_policy_delete_requires_permission()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Test',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'type' => 'service',
            'status' => 'scheduled',
        ]);

        // Admin has 'delete records' permission
        $this->assertTrue($this->admin->can('delete', $appointment));

        // Technician does not
        $this->assertFalse($this->technician->can('delete', $appointment));
    }

    #[Test]
    public function appointment_policy_denies_cross_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'title' => 'Test',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'type' => 'service',
            'status' => 'scheduled',
        ]);

        $this->assertFalse($this->otherUser->can('view', $appointment));
        $this->assertFalse($this->otherUser->can('update', $appointment));
    }

    // ─── CustomerPolicy ───

    #[Test]
    public function customer_policy_allows_same_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertTrue($this->admin->can('view', $customer));
        $this->assertTrue($this->admin->can('update', $customer));
        $this->assertTrue($this->admin->can('delete', $customer));
    }

    #[Test]
    public function customer_policy_denies_cross_tenant()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($this->otherUser->can('view', $customer));
        $this->assertFalse($this->otherUser->can('update', $customer));
    }

    // ─── Gate-based authorization ───

    #[Test]
    public function view_financials_gate_allows_admin()
    {
        $this->assertTrue($this->admin->can('view-financials'));
    }

    #[Test]
    public function view_financials_gate_denies_receptionist()
    {
        $this->assertFalse($this->receptionist->can('view-financials'));
    }

    #[Test]
    public function perform_admin_actions_gate_allows_admin()
    {
        $this->assertTrue($this->admin->can('perform-admin-actions'));
    }

    #[Test]
    public function perform_admin_actions_gate_denies_technician()
    {
        $this->assertFalse($this->technician->can('perform-admin-actions'));
    }

    #[Test]
    public function delete_records_gate_allows_admin()
    {
        $this->assertTrue($this->admin->can('delete-records'));
    }

    #[Test]
    public function delete_records_gate_denies_technician()
    {
        $this->assertFalse($this->technician->can('delete-records'));
    }
}
