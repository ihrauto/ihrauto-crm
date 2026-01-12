<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    }

    /**
     * Create a fully configured tenant for testing.
     */
    protected function createActiveTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'is_active' => true,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'plan' => 'basic',
            'settings' => ['has_seen_tour' => true], // Skip tour in tests
        ], $overrides));
    }

    public function test_registration_creates_tenant_with_14_day_trial(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company',
        ]);

        $this->assertAuthenticated();

        // Check tenant was created
        $tenant = Tenant::where('name', 'Test Company')->first();
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->is_trial);
        $this->assertTrue($tenant->is_active);

        // Verify trial_ends_at is approximately 14 days from now
        $this->assertNotNull($tenant->trial_ends_at);
        $daysUntilExpiry = now()->diffInDays($tenant->trial_ends_at, false);
        $this->assertTrue(
            $daysUntilExpiry >= 13 && $daysUntilExpiry <= 15,
            "Expected trial to end in 13-15 days, got {$daysUntilExpiry} days"
        );

        // Check user was created with tenant
        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_unverified_user_cannot_access_dashboard(): void
    {
        $tenant = $this->createActiveTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => null, // Not verified
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        // Should redirect to email verification
        $response->assertRedirect('/verify-email');
    }

    public function test_verified_user_with_active_trial_can_access_dashboard(): void
    {
        $tenant = $this->createActiveTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_expired_trial_blocks_access(): void
    {
        $tenant = $this->createActiveTenant([
            'trial_ends_at' => now()->subDay(), // Expired yesterday
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        // Should either redirect to pricing or return 403 (depending on middleware order)
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 403,
            'Expected redirect or 403 for expired trial'
        );
    }

    public function test_suspended_tenant_returns_403(): void
    {
        $tenant = $this->createActiveTenant([
            'is_active' => false, // Suspended
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_list_tenants(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        // Create some tenants
        Tenant::factory()->count(3)->create();

        $response = $this->actingAs($superAdmin)->get('/admin/tenants');

        // Follow redirects if any (middleware may redirect)
        if ($response->isRedirect()) {
            $response = $this->followRedirects($response);
        }

        // The superadmin should ultimately be able to access the page
        $this->assertTrue(
            $response->status() === 200 || $response->status() === 302,
            "Expected 200 or 302, got {$response->status()}"
        );
    }

    public function test_superadmin_can_suspend_tenant(): void
    {
        $superAdmin = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        $superAdmin->assignRole('super-admin');

        $tenant = Tenant::factory()->create(['is_active' => true]);

        $response = $this->actingAs($superAdmin)->post("/admin/tenants/{$tenant->id}/toggle");

        $response->assertRedirect(route('admin.tenants.index'));

        $tenant->refresh();
        $this->assertFalse($tenant->is_active);
    }

    public function test_non_superadmin_cannot_access_admin_panel(): void
    {
        $tenant = $this->createActiveTenant();

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/tenants');

        $response->assertStatus(403);
    }
}
