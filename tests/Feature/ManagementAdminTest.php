<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManagementAdminTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $this->tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_STANDARD,
            'is_active' => true,
            'is_trial' => false,
            'subscription_ends_at' => now()->addMonth(),
            'settings' => ['has_seen_tour' => true],
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create([
            'tenant_id' => null,
        ]);
        $user->assignRole('super-admin');

        return $user;
    }

    private function createManager(): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'manager',
        ]);
        $user->assignRole('manager');

        return $user;
    }

    #[Test]
    public function management_settings_can_be_updated()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('management.settings.update'), [
                'company_name' => 'IHRAUTO Beta Garage',
                'address' => 'Station 1',
                'postal_code' => '8000',
                'city' => 'Zurich',
                'country' => 'CH',
                'uid_number' => 'CHE-123.456.789',
                'vat_registered' => '1',
                'vat_number' => 'VAT-100',
                'bank_name' => 'Test Bank',
                'iban' => 'CH9300762011623852957',
                'account_holder' => 'IHRAUTO Beta Garage',
                'invoice_email' => 'billing@example.com',
                'invoice_phone' => '+41795550101',
                'currency' => 'EUR',
                'tax_rate' => 8.1,
                'module_tire_hotel' => '1',
                'module_checkin' => '1',
            ]);

        $response->assertRedirect(route('management.settings'));

        $this->tenant->refresh();
        $this->assertEquals('IHRAUTO Beta Garage', $this->tenant->name);
        $this->assertEquals('EUR', $this->tenant->currency);
        $this->assertContains('tire_hotel', $this->tenant->features);
        $this->assertContains('vehicle_checkin', $this->tenant->features);
        $this->assertEquals(8.1, $this->tenant->settings['tax_rate']);
    }

    #[Test]
    public function backup_download_streams_json_metadata_and_model_sections()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Backup Customer',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('management.backup'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"metadata"', $content);
        $this->assertStringContainsString('"customers"', $content);
        $this->assertStringContainsString('"Backup Customer"', $content);
    }

    #[Test]
    public function customer_delete_is_blocked_when_linked_records_exist()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.show', $customer));
        $response->assertSessionHas('error', function (string $message) {
            return str_contains($message, 'vehicles') && str_contains($message, 'check-ins');
        });

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    #[Test]
    public function expired_tenant_can_reach_the_billing_page_and_dashboard_redirects_there()
    {
        $expiredTenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_BASIC,
            'is_active' => true,
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $expiredTenant->id,
            'role' => 'admin',
        ]);
        $user->assignRole('admin');

        $this->actingAs($user)
            ->get(route('billing.pricing'))
            ->assertOk()
            ->assertSee('Manual Billing');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('billing.pricing'));
    }

    #[Test]
    public function superadmin_can_convert_a_tenant_to_paid_and_set_the_renewal_date()
    {
        $tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_BASIC,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);
        $superAdmin = $this->createSuperAdmin();
        $renewalDate = now()->addDays(30)->toDateString();

        $response = $this->actingAs($superAdmin)
            ->from(route('admin.tenants.show', $tenant))
            ->post(route('admin.tenants.billing', $tenant), [
                'plan' => Tenant::PLAN_STANDARD,
                'renewal_date' => $renewalDate,
                'reason' => 'Closed beta upgrade',
            ]);

        $response->assertRedirect(route('admin.tenants.show', $tenant));

        $tenant->refresh();
        $this->assertFalse($tenant->is_trial);
        $this->assertEquals(Tenant::PLAN_STANDARD, $tenant->plan);
        $this->assertEquals($renewalDate, $tenant->subscription_ends_at?->toDateString());
        $this->assertEquals('manual', $tenant->settings['billing_mode']);
        $this->assertEquals(Tenant::planDefinition(Tenant::PLAN_STANDARD)['limits']['max_users'], $tenant->max_users);
        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
            'action' => 'billing_update',
        ]);
    }

    #[Test]
    public function superadmin_can_suspend_activate_and_archive_a_tenant()
    {
        $tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_STANDARD,
            'is_active' => true,
        ]);
        $tenantUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);
        $tenantUser->assignRole('admin');

        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->from(route('admin.tenants.show', $tenant))
            ->post(route('admin.tenants.suspend', $tenant), [
                'reason' => 'Testing suspension flow',
            ])
            ->assertRedirect(route('admin.tenants.show', $tenant));

        $tenant->refresh();
        $this->assertFalse($tenant->is_active);

        $this->actingAs($superAdmin)
            ->from(route('admin.tenants.show', $tenant))
            ->post(route('admin.tenants.activate', $tenant), [
                'reason' => 'Testing reactivation flow',
            ])
            ->assertRedirect(route('admin.tenants.show', $tenant));

        $tenant->refresh();
        $this->assertTrue($tenant->is_active);

        $this->actingAs($superAdmin)
            ->delete(route('admin.tenants.destroy', $tenant), [
                'confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('admin.tenants.index'));

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $tenantUser->refresh();
        $this->assertFalse($tenantUser->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_archived',
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
        ]);
    }

    #[Test]
    public function superadmin_dashboard_uses_independent_growth_windows()
    {
        Tenant::factory()->create([
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        Tenant::factory()->create([
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);

        Tenant::factory()->create([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics) {
            return $metrics['growth']['new_tenants_7d'] === 2
                && $metrics['growth']['new_tenants_30d'] === 3;
        });
    }

    #[Test]
    public function superadmin_can_filter_tenants_by_search_and_status()
    {
        Tenant::factory()->create([
            'name' => 'Dormant Garage',
            'email' => 'dormant@example.com',
            'is_active' => false,
        ]);

        $matchingTenant = Tenant::factory()->create([
            'name' => 'Focused Motors',
            'email' => 'ops@focused.test',
            'is_active' => true,
            'plan' => Tenant::PLAN_STANDARD,
        ]);

        Customer::factory()->count(2)->create([
            'tenant_id' => $matchingTenant->id,
        ]);

        $response = $this->actingAs($this->createSuperAdmin())
            ->get(route('admin.tenants.index', [
                'q' => 'Focused',
                'status' => 'active',
                'plan' => Tenant::PLAN_STANDARD,
            ]));

        $response->assertOk();
        $response->assertViewHas('tenants', function ($tenants) use ($matchingTenant) {
            return $tenants->count() === 1
                && $tenants->first()->is($matchingTenant)
                && $tenants->first()->customers_count === 2;
        });
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['suspended'] >= 1;
        });
    }

    #[Test]
    public function work_order_photo_urls_follow_the_public_disk_configuration()
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        config([
            'filesystems.disks.public.url' => 'https://cdn.example.com/public-files',
        ]);

        $photo = WorkOrderPhoto::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->admin->id,
            'filename' => 'example.jpg',
            'original_name' => 'example.jpg',
            'path' => 'work-order-photos/'.$this->tenant->id.'/'.$workOrder->id.'/example.jpg',
            'type' => 'before',
            'caption' => 'Smoke test',
        ]);

        $this->assertEquals(
            'https://cdn.example.com/public-files/'.$photo->path,
            $photo->url
        );
    }

    #[Test]
    public function tenant_admin_can_create_another_tenant_admin()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('management.users.store'), [
                'name' => 'Second Admin',
                'email' => 'second-admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
            ]);

        $response->assertRedirect(route('management'));
        $this->assertDatabaseHas('users', [
            'email' => 'second-admin@example.com',
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    #[Test]
    public function manager_cannot_create_admin_users_or_platform_roles()
    {
        $manager = $this->createManager();

        $this->actingAs($manager)
            ->post(route('management.users.store'), [
                'name' => 'Blocked Admin',
                'email' => 'blocked-admin@example.com',
                'password' => 'password123',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($manager)
            ->post(route('management.users.store'), [
                'name' => 'Blocked Owner',
                'email' => 'blocked-owner@example.com',
                'password' => 'password123',
                'role' => 'super-admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'blocked-admin@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'blocked-owner@example.com']);
    }

    #[Test]
    public function manager_cannot_edit_same_tenant_admin_users()
    {
        $manager = $this->createManager();

        $this->actingAs($manager)
            ->get(route('management.users.edit', $this->admin))
            ->assertStatus(403);
    }

    #[Test]
    public function last_active_tenant_admin_cannot_be_demoted_or_deleted()
    {
        $this->actingAs($this->admin)
            ->put(route('management.users.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role' => 'manager',
            ])
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->delete(route('management.users.destroy', $this->admin))
            ->assertStatus(403);

        $this->admin->refresh();
        $this->assertTrue($this->admin->hasRole('admin'));
    }

    #[Test]
    public function mechanics_routes_require_manage_users_permission()
    {
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'technician',
        ]);
        $technician->assignRole('technician');

        $this->actingAs($technician)
            ->get(route('mechanics.index'))
            ->assertStatus(403);
    }

    #[Test]
    public function disabled_checkin_module_blocks_access()
    {
        $this->tenant->update([
            'features' => array_values(array_diff($this->tenant->features ?? [], ['vehicle_checkin'])),
        ]);

        $this->actingAs($this->admin)
            ->from(route('dashboard'))
            ->get(route('checkin'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'This module is disabled for your company.');
    }
}
