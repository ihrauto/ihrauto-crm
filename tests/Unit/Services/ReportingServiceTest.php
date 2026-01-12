<?php

namespace Tests\Unit\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportingService $service;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportingService;

        // Create a tenant and user for testing
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_get_kpis()
    {
        $kpis = $this->service->getKPIs();

        $this->assertIsArray($kpis);
        $this->assertArrayHasKey('monthly_revenue', $kpis);
        $this->assertArrayHasKey('service_completion_rate', $kpis);
        $this->assertArrayHasKey('storage_utilization', $kpis);
    }

    /** @test */
    public function it_calculates_monthly_revenue_with_growth()
    {
        // Create completed checkins for this month
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'checkout_time' => now(),
            'actual_cost' => 500,
        ]);

        $result = $this->service->getMonthlyRevenue();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('current', $result);
        $this->assertArrayHasKey('growth', $result);
        $this->assertArrayHasKey('trend', $result);
        $this->assertEquals(500, $result['current']);
    }

    /** @test */
    public function it_calculates_service_completion_rate()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create 4 checkins: 3 completed, 1 pending
        Checkin::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'checkout_time' => now(),
        ]);

        Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $result = $this->service->getServiceCompletionRate();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rate', $result);
        $this->assertArrayHasKey('completed', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(3, $result['completed']);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(75.0, $result['rate']);
    }

    /** @test */
    public function it_calculates_storage_utilization()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create stored tires
        Tire::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'stored',
            'quantity' => 4,
        ]);

        $result = $this->service->getStorageUtilization();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('used', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(4, $result['used']);
    }

    /** @test */
    public function it_returns_performance_metrics_for_7_days()
    {
        $result = $this->service->getPerformanceMetrics();

        $this->assertIsArray($result);
        $this->assertCount(7, $result);

        foreach ($result as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertArrayHasKey('checkins', $day);
            $this->assertArrayHasKey('completed', $day);
            $this->assertArrayHasKey('revenue', $day);
        }
    }

    /** @test */
    public function it_returns_customer_analytics()
    {
        Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $result = $this->service->getCustomerAnalytics();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('new_this_month', $result);
        $this->assertArrayHasKey('active', $result);
        $this->assertArrayHasKey('top_customers', $result);
    }

    /** @test */
    public function it_returns_system_alerts_collection()
    {
        $alerts = $this->service->getSystemAlerts();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $alerts);
    }
}
