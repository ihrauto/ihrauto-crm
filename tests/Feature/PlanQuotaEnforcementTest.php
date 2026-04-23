<?php

namespace Tests\Feature;

use App\Exceptions\PlanQuotaExceededException;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-01 regression tests — plan quotas must be enforced server-side,
 * not just in the UI.
 */
class PlanQuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_assert_can_add_customer_throws_when_limit_reached(): void
    {
        $tenant = Tenant::factory()->create([
            'max_customers' => 2,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($user);
        app(\App\Support\TenantContext::class)->set($tenant);

        Customer::create(['tenant_id' => $tenant->id, 'name' => 'A', 'phone' => '1']);
        Customer::create(['tenant_id' => $tenant->id, 'name' => 'B', 'phone' => '2']);

        // Clear cached count so canAddCustomer reflects the 2 we just inserted.
        cache()->forget("tenant_{$tenant->id}_customer_count");

        $this->expectException(PlanQuotaExceededException::class);
        PlanQuota::assertCanAddCustomer($tenant);
    }

    public function test_assert_can_add_customer_passes_when_under_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'max_customers' => 5,
            'is_active' => true,
        ]);

        Customer::create(['tenant_id' => $tenant->id, 'name' => 'A', 'phone' => '1']);
        cache()->forget("tenant_{$tenant->id}_customer_count");

        PlanQuota::assertCanAddCustomer($tenant);
        $this->assertTrue(true); // no exception thrown
    }

    public function test_assert_can_create_work_order_respects_monthly_cap_on_basic(): void
    {
        $tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_BASIC,
            'max_work_orders' => 0, // zero allowed this month
            'is_active' => true,
        ]);

        $this->expectException(PlanQuotaExceededException::class);
        PlanQuota::assertCanCreateWorkOrder($tenant);
    }

    public function test_assert_can_create_work_order_unlimited_on_standard(): void
    {
        $tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_STANDARD,
            'max_work_orders' => 0,
            'is_active' => true,
        ]);

        PlanQuota::assertCanCreateWorkOrder($tenant);
        $this->assertTrue(true); // no exception
    }

    public function test_null_tenant_is_noop(): void
    {
        // Console / superadmin flows have no tenant — quota must not throw.
        PlanQuota::assertCanCreateWorkOrder(null);
        PlanQuota::assertCanAddCustomer(null);
        PlanQuota::assertCanAddVehicle(null);
        PlanQuota::assertCanAddUser(null);

        $this->assertTrue(true);
    }

    public function test_exception_renders_402_json_for_api_requests(): void
    {
        $exception = new PlanQuotaExceededException('customers', 10, 'Limit reached.');

        $request = \Illuminate\Http\Request::create('/api/test', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $exception->render($request);

        $this->assertSame(402, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('plan_quota_exceeded', $body['error']);
        $this->assertSame('customers', $body['quota']);
        $this->assertSame(10, $body['limit']);
    }
}
