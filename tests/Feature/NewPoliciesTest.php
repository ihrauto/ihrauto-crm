<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the per-instance contracts of the three policies added in
 * the audit-remediation pass: Payment, User, Tenant. Route-level
 * permission middleware was already covered elsewhere; these tests
 * only verify the policy decisions themselves.
 */
class NewPoliciesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $otherTenant;

    protected User $admin;

    protected User $manager;

    protected User $technician;

    protected User $otherAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->manager->assignRole('manager');

        $this->technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->technician->assignRole('technician');

        $this->otherAdmin = User::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $this->otherAdmin->assignRole('admin');
    }

    // ────────── PaymentPolicy ──────────

    #[Test]
    public function payment_policy_blocks_update_and_delete_for_everyone()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'P-T-1',
            'status' => 'issued',
            'issue_date' => now(),
            'total' => 100,
        ]);
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'method' => 'cash',
            'payment_date' => now(),
        ]);

        // Even an admin cannot mutate or delete an existing payment —
        // the void flow uses reversing payments instead.
        $this->assertFalse($this->admin->can('update', $payment));
        $this->assertFalse($this->admin->can('delete', $payment));
        $this->assertFalse($this->admin->can('forceDelete', $payment));
    }

    #[Test]
    public function payment_policy_view_is_tenant_scoped()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $invoice = Invoice::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'P-T-2',
            'status' => 'issued',
            'issue_date' => now(),
            'total' => 100,
        ]);
        $payment = Payment::create([
            'tenant_id' => $this->tenant->id,
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'method' => 'cash',
            'payment_date' => now(),
        ]);

        $this->assertTrue($this->admin->can('view', $payment));
        $this->assertFalse($this->otherAdmin->can('view', $payment));
    }

    // ────────── UserPolicy ──────────

    #[Test]
    public function user_policy_denies_self_delete()
    {
        // Even an admin in their own tenant must not be allowed to
        // delete themselves — that would lock them out of their session
        // and (if they're the last admin) the tenant.
        $this->assertFalse($this->admin->can('delete', $this->admin));
    }

    #[Test]
    public function user_policy_is_tenant_scoped()
    {
        $this->assertTrue($this->admin->can('view', $this->technician));
        $this->assertFalse($this->otherAdmin->can('view', $this->technician));
        $this->assertFalse($this->otherAdmin->can('update', $this->technician));
        $this->assertFalse($this->otherAdmin->can('delete', $this->technician));
    }

    #[Test]
    public function user_policy_requires_manage_users_permission()
    {
        // Technician cannot manage other users.
        $this->assertFalse($this->technician->can('view', $this->admin));
        $this->assertFalse($this->technician->can('update', $this->admin));
    }

    // ────────── TenantPolicy ──────────

    #[Test]
    public function tenant_policy_view_is_self_only()
    {
        $this->assertTrue($this->admin->can('view', $this->tenant));
        $this->assertFalse($this->admin->can('view', $this->otherTenant));
    }

    #[Test]
    public function tenant_policy_update_requires_manage_settings_permission()
    {
        // Admin has manage settings; can update own tenant only.
        $this->assertTrue($this->admin->can('update', $this->tenant));
        $this->assertFalse($this->admin->can('update', $this->otherTenant));

        // Manager: depends on whether their permission set includes
        // 'manage settings' — per the seeder it doesn't.
        $this->assertFalse($this->manager->can('update', $this->tenant));
    }

    #[Test]
    public function tenant_policy_blocks_lifecycle_transitions_for_tenant_admin()
    {
        // Tenant deletion / suspension are super-admin only. Even an
        // in-tenant admin gets a hard `false`.
        $this->assertFalse($this->admin->can('delete', $this->tenant));
        $this->assertFalse($this->admin->can('suspend', $this->tenant));
        $this->assertFalse($this->admin->can('forceDelete', $this->tenant));
        $this->assertFalse($this->admin->can('create', Tenant::class));
    }
}
