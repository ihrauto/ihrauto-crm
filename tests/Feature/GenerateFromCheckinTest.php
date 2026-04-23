<?php

namespace Tests\Feature;

use App\Enums\CheckinStatus;
use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C-01 deep regression — generateFromCheckin must be idempotent, respect
 * plan quotas, and keep the checkin's status in sync inside one
 * transaction.
 */
class GenerateFromCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function bootstrapCheckin(?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::factory()->create(['is_active' => true]);
        app(TenantContext::class)->set($tenant);

        $customer = Customer::create([
            'tenant_id' => $tenant->id, 'name' => 'C', 'phone' => '1',
        ]);
        $vehicle = Vehicle::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'license_plate' => 'ZH-GEN-1',
            'make' => 'M', 'model' => 'Q', 'year' => 2020,
        ]);
        $checkin = Checkin::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => 'oil_change, inspection',
            'service_description' => 'Routine service',
            'priority' => 'medium',
            'service_bay' => 'Bay 1',
            'status' => 'pending',
            'checkin_time' => now(),
        ]);

        return [$tenant, $checkin];
    }

    public function test_creates_work_order_from_checkin_and_advances_checkin_status(): void
    {
        [, $checkin] = $this->bootstrapCheckin();
        $user = User::factory()->create(['tenant_id' => $checkin->tenant_id]);

        $wo = app(WorkOrderService::class)->generateFromCheckin($checkin, $user->id);

        $this->assertNotNull($wo->id);
        $this->assertSame($checkin->id, $wo->checkin_id);
        $this->assertSame($user->id, $wo->technician_id);
        $this->assertIsArray($wo->service_tasks);
        $this->assertNotEmpty($wo->service_tasks);
        $this->assertSame(CheckinStatus::InProgress->value, $checkin->fresh()->status);
    }

    public function test_is_idempotent(): void
    {
        [, $checkin] = $this->bootstrapCheckin();

        $first = app(WorkOrderService::class)->generateFromCheckin($checkin);
        $second = app(WorkOrderService::class)->generateFromCheckin($checkin->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WorkOrder::where('checkin_id', $checkin->id)->count());
    }

    public function test_respects_plan_quota(): void
    {
        $tenant = Tenant::factory()->create([
            'is_active' => true,
            'plan' => Tenant::PLAN_BASIC,
            'max_work_orders' => 0,
        ]);
        [, $checkin] = $this->bootstrapCheckin($tenant);

        $this->expectException(\App\Exceptions\PlanQuotaExceededException::class);
        app(WorkOrderService::class)->generateFromCheckin($checkin);
    }
}
