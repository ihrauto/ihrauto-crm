<?php

namespace Tests\Feature\Api;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantApiToken;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rejects_requests_without_bearer_token(): void
    {
        $this->getJson('/api/v1/checkins/active')
            ->assertStatus(401)
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    public function test_api_token_only_exposes_active_tenant_records(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        [, $plainTextToken] = TenantApiToken::issue($tenantA, 'test-suite');

        $customerA = Customer::factory()->create(['tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $vehicleA = Vehicle::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
        ]);
        $vehicleB = Vehicle::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
        ]);

        Checkin::factory()->create([
            'tenant_id' => $tenantA->id,
            'customer_id' => $customerA->id,
            'vehicle_id' => $vehicleA->id,
            'status' => 'pending',
        ]);
        Checkin::factory()->create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'vehicle_id' => $vehicleB->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/checkins/active');

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.customer.id', $customerA->id);
    }

    public function test_legacy_api_route_requires_token_and_returns_deprecation_headers(): void
    {
        $tenant = Tenant::factory()->create();
        [, $plainTextToken] = TenantApiToken::issue($tenant, 'legacy-test');
        $customer = Customer::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Legacy Customer',
            'email' => 'legacy@example.com',
        ]);
        Vehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'license_plate' => 'ZH1001',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/customers/search?query=legacy');

        $response->assertOk();
        $response->assertHeader('Deprecation', 'true');
        $response->assertHeader('Link', '</api/v1>; rel="successor-version"');
    }

    public function test_revoked_token_is_rejected_immediately_even_after_cache_warmup(): void
    {
        $tenant = Tenant::factory()->create();
        [$tokenModel, $plainTextToken] = TenantApiToken::issue($tenant, 'revocation-test');

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/checkins/active')
            ->assertOk();

        $tokenModel->revoke();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/checkins/active')
            ->assertStatus(401);
    }

    public function test_suspended_tenant_token_is_rejected_immediately_even_after_cache_warmup(): void
    {
        $tenant = Tenant::factory()->create();
        [, $plainTextToken] = TenantApiToken::issue($tenant, 'suspension-test');

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/checkins/active')
            ->assertOk();

        $tenant->suspend();

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/checkins/active')
            ->assertStatus(403)
            ->assertJsonPath('error', 'Tenant inactive');
    }
}
