<?php

namespace Tests\Unit\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardService $service;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DashboardService;

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_returns_complete_stats_array()
    {
        $stats = $this->service->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_customers', $stats);
        $this->assertArrayHasKey('active_checkins', $stats);
        $this->assertArrayHasKey('tires_in_hotel', $stats);
        $this->assertArrayHasKey('monthly_revenue', $stats);
        $this->assertArrayHasKey('overdue_invoices_count', $stats);
        $this->assertArrayHasKey('total_outstanding', $stats);
        $this->assertArrayHasKey('customer_growth', $stats);
        $this->assertArrayHasKey('revenue_growth', $stats);
    }

    /** @test */
    public function it_counts_active_customers()
    {
        Customer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $stats = $this->service->getStats();

        $this->assertEquals(3, $stats['total_customers']);
    }

    /** @test */
    public function it_counts_checkins_by_status()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        Checkin::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
        ]);

        Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'in_progress',
        ]);

        $stats = $this->service->getStats();

        $this->assertEquals(2, $stats['pending_checkins']);
        $this->assertEquals(1, $stats['in_progress_checkins']);
    }

    /** @test */
    public function it_returns_recent_activities_collection()
    {
        $activities = $this->service->getRecentActivities();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $activities);
    }

    /** @test */
    public function it_returns_recent_checkins_with_required_fields()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $checkins = $this->service->getRecentCheckins();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $checkins);

        if ($checkins->isNotEmpty()) {
            $first = $checkins->first();
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('customer_name', $first);
            $this->assertArrayHasKey('vehicle_name', $first);
            $this->assertArrayHasKey('status', $first);
        }
    }

    /** @test */
    public function it_returns_service_bay_status_using_config()
    {
        $bays = $this->service->getServiceBayStatus();

        $this->assertIsArray($bays);

        $configBays = config('crm.service_bays.names');
        foreach ($configBays as $bayName) {
            $this->assertArrayHasKey($bayName, $bays);
        }
    }

    /** @test */
    public function it_returns_tire_operations_collection()
    {
        $operations = $this->service->getTireOperations();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $operations);
    }

    /** @test */
    public function it_returns_system_status_array()
    {
        $status = $this->service->getSystemStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('database', $status);
        $this->assertArrayHasKey('storage', $status);
        $this->assertArrayHasKey('cache', $status);
        $this->assertArrayHasKey('backup', $status);
    }
}
