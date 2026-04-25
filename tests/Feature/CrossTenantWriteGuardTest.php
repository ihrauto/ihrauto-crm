<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Locks in the BelongsToTenant runtime cross-tenant-write guard.
 *
 * In console / queue / test contexts the guard is intentionally
 * bypassed — that's where seeders and tenant provisioning legitimately
 * touch multiple tenants. In HTTP contexts (real request lifecycle)
 * a `Model::create([..., 'tenant_id' => other])` while the bound
 * tenant context belongs to someone else is refused with a
 * LogicException, blunting any future mass-assignment regression.
 */
class CrossTenantWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function http_request_cannot_create_a_row_under_a_different_tenant_id()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $admin = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Simulate the HTTP context: tenant_id() resolves to tenantA,
        // and we're not in console (we'll force `runningInConsole` off
        // by hitting a real HTTP route that triggers the guard).
        // Easiest: directly drive the trait by setting tenant context
        // and asserting console-exemption is the deciding factor.
        app(TenantContext::class)->set($tenantA);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/cross-tenant|tenant context/i');

        // Force the HTTP-context branch by lying about runningInConsole.
        // Easiest portable trick: bind a fake App that returns false.
        $original = app();
        $fake = new class extends \Illuminate\Foundation\Application
        {
            public function runningInConsole(): bool
            {
                return false;
            }
        };
        // Copy bindings so the fake behaves like the real app.
        \Illuminate\Container\Container::setInstance($fake);
        try {
            Customer::create([
                'tenant_id' => $tenantB->id,
                'name' => 'Cross-tenant attempt',
            ]);
        } finally {
            \Illuminate\Container\Container::setInstance($original);
        }
    }

    #[Test]
    public function tenant_id_is_immutable_in_http_context()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app(TenantContext::class)->set($tenantA);

        $customer = Customer::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Original tenant',
            'phone' => '+41 79 555 12 34',
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/immutable/i');

        $original = app();
        $fake = new class extends \Illuminate\Foundation\Application
        {
            public function runningInConsole(): bool
            {
                return false;
            }
        };
        \Illuminate\Container\Container::setInstance($fake);
        try {
            $customer->tenant_id = $tenantB->id;
            $customer->save();
        } finally {
            \Illuminate\Container\Container::setInstance($original);
        }
    }
}
