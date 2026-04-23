<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C-09 regression — every tenant-API endpoint (except the legacy bare-
 * array search) returns the unified envelope:
 *   { success: bool, data: ..., meta: {...}? }
 * or
 *   { success: false, message: string, error?: string }
 */
class ApiResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    private function tokenHeader(Tenant $tenant): array
    {
        [, $plain] = TenantApiToken::issue($tenant, 'test');

        return ['Authorization' => 'Bearer '.$plain];
    }

    public function test_checkin_customer_history_returns_unified_envelope(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'Shape', 'phone' => '1',
        ]);

        $response = $this->getJson(
            "/api/v1/customers/{$customer->id}/history",
            $this->tokenHeader($tenant)
        );

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['customer', 'checkins'],
                'meta' => ['total', 'customer_id'],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_missing_token_returns_401_envelope(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'NoAuth', 'phone' => '1',
        ]);

        $this->getJson("/api/v1/customers/{$customer->id}/history")
            ->assertStatus(401)
            ->assertJsonStructure(['error', 'message']);
    }

    public function test_cross_tenant_customer_returns_404_not_403(): void
    {
        $tenantA = Tenant::factory()->create(['is_active' => true]);
        $tenantB = Tenant::factory()->create(['is_active' => true]);

        $customerB = Customer::create([
            'tenant_id' => $tenantB->id, 'name' => 'Foreign', 'phone' => '9',
        ]);

        // Use tenantA's token to read tenantB's customer — must 404, not
        // leak existence via a 403.
        $this->getJson(
            "/api/v1/customers/{$customerB->id}/history",
            $this->tokenHeader($tenantA)
        )->assertStatus(404);
    }
}
