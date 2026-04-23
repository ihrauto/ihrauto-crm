<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create([
            'is_active' => true,
            'plan' => 'standard',
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');
    }

    // ─── EnsureTenantTrialActive ───

    #[Test]
    public function active_tenant_user_can_access_dashboard()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertStatus(200);
    }

    #[Test]
    public function inactive_tenant_user_gets_403()
    {
        $this->tenant->update(['is_active' => false]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        $response->assertStatus(403);
    }

    #[Test]
    public function expired_trial_tenant_gets_redirected_to_billing()
    {
        $this->tenant->update([
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
            'plan' => 'trial',
            'subscription_ends_at' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard'));

        // Expired trial should redirect to billing pricing page
        $response->assertRedirect(route('billing.pricing'));
    }

    // ─── CheckModuleAccess ───

    #[Test]
    public function admin_can_access_checkin_module()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('checkin'));

        $response->assertStatus(200);
    }

    #[Test]
    public function receptionist_cannot_access_inventory_module()
    {
        $receptionist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'receptionist',
        ]);
        $receptionist->assignRole('receptionist');

        $response = $this->actingAs($receptionist)
            ->get(route('products-services.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function receptionist_cannot_access_management_module()
    {
        $receptionist = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'receptionist',
        ]);
        $receptionist->assignRole('receptionist');

        $response = $this->actingAs($receptionist)
            ->get(route('management'));

        $response->assertStatus(403);
    }

    // ─── RequireTireHotelAccess ───

    #[Test]
    public function standard_plan_can_access_tire_hotel()
    {
        $this->tenant->update(['plan' => 'standard']);

        $response = $this->actingAs($this->admin)
            ->get(route('tires-hotel'));

        $response->assertStatus(200);
    }

    #[Test]
    public function basic_plan_cannot_access_tire_hotel()
    {
        $this->tenant->update(['plan' => 'basic']);

        $response = $this->actingAs($this->admin)
            ->get(route('tires-hotel'));

        $response->assertRedirect(route('dashboard'));
    }

    // ─── Unauthenticated access ───

    #[Test]
    public function unauthenticated_user_is_redirected_to_login()
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function unauthenticated_user_cannot_access_checkin()
    {
        $response = $this->get(route('checkin'));

        $response->assertRedirect(route('login'));
    }
}
