<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-09 regression — customers.email is globally NON-unique, but
 * `(tenant_id, email)` must be unique. Two tenants can both have a
 * customer named hello@example.com; the same tenant cannot.
 */
class CustomerEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_email_allowed_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['is_active' => true]);
        $tenantB = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenantA);

        Customer::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Alice', 'phone' => '1',
            'email' => 'hello@example.com',
        ]);

        app(TenantContext::class)->clear();
        app(TenantContext::class)->set($tenantB);

        $second = Customer::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Bob', 'phone' => '2',
            'email' => 'hello@example.com',
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_same_email_rejected_within_same_tenant(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alice', 'phone' => '1',
            'email' => 'dup@example.com',
        ]);

        $this->expectException(QueryException::class);

        Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alice v2', 'phone' => '2',
            'email' => 'dup@example.com',
        ]);
    }
}
