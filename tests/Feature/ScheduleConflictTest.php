<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * B-03 regression tests — prevent double-booking on bay / technician.
 */
class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderService $service;

    private Tenant $tenant;

    private User $technician;

    private Customer $customer;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkOrderService::class);
        $this->tenant = Tenant::factory()->create(['is_active' => true]);
        app(\App\Support\TenantContext::class)->set($this->tenant);

        $this->technician = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Schedule Test',
            'phone' => '+41791234567',
        ]);
        $this->vehicle = Vehicle::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'license_plate' => 'ZH-TEST-1',
            'make' => 'M',
            'model' => 'Q',
            'year' => 2020,
        ]);
    }

    public function test_technician_overlap_returns_conflict(): void
    {
        $start = Carbon::parse('2026-05-01 10:00');

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'scheduled',
            'scheduled_at' => $start,
            'estimated_minutes' => 90,
            'technician_id' => $this->technician->id,
            'service_bay' => 2,
        ]);

        $conflict = $this->service->findScheduleConflict(
            start: $start->copy()->addMinutes(30), // overlaps 10:30-11:30 with 10:00-11:30
            estimatedMinutes: 60,
            technicianId: $this->technician->id,
            serviceBay: 5, // different bay so only technician overlaps
        );

        $this->assertNotNull($conflict);
        $this->assertStringContainsString('Technician', $conflict);
    }

    public function test_bay_overlap_returns_conflict(): void
    {
        $start = Carbon::parse('2026-05-01 14:00');

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'scheduled',
            'scheduled_at' => $start,
            'estimated_minutes' => 60,
            'service_bay' => 3,
        ]);

        $conflict = $this->service->findScheduleConflict(
            start: $start->copy()->addMinutes(15), // overlaps
            estimatedMinutes: 30,
            technicianId: null,
            serviceBay: 3,
        );

        $this->assertNotNull($conflict);
        $this->assertStringContainsString('bay', strtolower($conflict));
    }

    public function test_no_overlap_returns_null(): void
    {
        $start = Carbon::parse('2026-05-01 09:00');

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'scheduled',
            'scheduled_at' => $start,
            'estimated_minutes' => 60,
            'technician_id' => $this->technician->id,
            'service_bay' => 2,
        ]);

        // Slot starts AFTER the other one ends (no overlap).
        $conflict = $this->service->findScheduleConflict(
            start: $start->copy()->addMinutes(90),
            estimatedMinutes: 30,
            technicianId: $this->technician->id,
            serviceBay: 2,
        );

        $this->assertNull($conflict);
    }

    public function test_completed_work_orders_do_not_conflict(): void
    {
        $start = Carbon::parse('2026-05-01 08:00');

        WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'completed', // completed = not a booking anymore
            'scheduled_at' => $start,
            'estimated_minutes' => 120,
            'technician_id' => $this->technician->id,
            'service_bay' => 1,
        ]);

        $conflict = $this->service->findScheduleConflict(
            start: $start->copy()->addMinutes(30),
            estimatedMinutes: 30,
            technicianId: $this->technician->id,
            serviceBay: 1,
        );

        $this->assertNull($conflict);
    }

    public function test_excluded_work_order_is_ignored(): void
    {
        // Simulates editing an existing WO — it should not conflict with itself.
        $start = Carbon::parse('2026-05-01 12:00');

        $wo = WorkOrder::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'scheduled',
            'scheduled_at' => $start,
            'estimated_minutes' => 60,
            'technician_id' => $this->technician->id,
            'service_bay' => 4,
        ]);

        $conflict = $this->service->findScheduleConflict(
            start: $start,
            estimatedMinutes: 60,
            technicianId: $this->technician->id,
            serviceBay: 4,
            excludeWorkOrderId: $wo->id,
        );

        $this->assertNull($conflict);
    }
}
