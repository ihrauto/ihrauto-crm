<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\FinanceService;
use App\Services\InvoiceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for Sprint A.1 — FinanceService raw query tenant leak.
 *
 * These tests verify that analytics queries never return rows from other tenants,
 * even when raw DB::table() joins or multi-table joins are used.
 */
class FinanceServiceTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected FinanceService $finance;

    protected InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finance = new FinanceService;
        $this->invoiceService = new InvoiceService;
        Cache::flush(); // Ensure clean cache between tests
    }

    #[Test]
    public function get_top_services_does_not_leak_across_tenants(): void
    {
        // Build tenant A with an "Oil Change" service totaling 100
        $tenantA = $this->buildTenantWithCompletedWorkOrder(
            serviceName: 'Oil Change - Tenant A',
            servicePrice: 100,
        );

        // Build tenant B with a "Tire Rotation" service totaling 500
        $tenantB = $this->buildTenantWithCompletedWorkOrder(
            serviceName: 'Tire Rotation - Tenant B',
            servicePrice: 500,
        );

        // Act as tenant A
        app(TenantContext::class)->set($tenantA);
        Cache::flush();

        $result = $this->finance->getTopServices();

        $names = collect($result)->pluck('name')->all();
        $this->assertContains('Oil Change - Tenant A', $names);
        $this->assertNotContains('Tire Rotation - Tenant B', $names, 'Cross-tenant data leak in getTopServices');
    }

    #[Test]
    public function get_top_services_as_tenant_b_sees_only_tenant_b_data(): void
    {
        $tenantA = $this->buildTenantWithCompletedWorkOrder('Service A', 100);
        $tenantB = $this->buildTenantWithCompletedWorkOrder('Service B', 200);

        app(TenantContext::class)->set($tenantB);
        Cache::flush();

        $result = $this->finance->getTopServices();
        $names = collect($result)->pluck('name')->all();

        $this->assertContains('Service B', $names);
        $this->assertNotContains('Service A', $names);
    }

    #[Test]
    public function get_technician_productivity_does_not_leak_across_tenants(): void
    {
        // Tenant A tech named Alice with 1 completed WO
        $tenantA = $this->buildTenantWithCompletedWorkOrder(
            serviceName: 'Brake Check',
            servicePrice: 150,
            technicianName: 'Alice',
        );

        // Tenant B tech named Bob with 1 completed WO
        $tenantB = $this->buildTenantWithCompletedWorkOrder(
            serviceName: 'Tune Up',
            servicePrice: 300,
            technicianName: 'Bob',
        );

        // Act as tenant A
        app(TenantContext::class)->set($tenantA);
        Cache::flush();

        $result = $this->finance->getTechnicianProductivity();

        $names = collect($result)->pluck('name')->all();
        $this->assertContains('Alice', $names);
        $this->assertNotContains('Bob', $names, 'Cross-tenant data leak in getTechnicianProductivity');
    }

    #[Test]
    public function get_overview_does_not_leak_across_tenants(): void
    {
        // Tenant A: 1 completed WO with invoice of 100
        $tenantA = $this->buildTenantWithCompletedWorkOrder('Service', 100);

        // Tenant B: 1 completed WO with invoice of 500
        $tenantB = $this->buildTenantWithCompletedWorkOrder('Service', 500);

        app(TenantContext::class)->set($tenantA);
        Cache::flush();

        $overview = $this->finance->getOverview();

        // Tenant A's unpaid total should only reflect tenant A's invoice (around 108.10 with tax)
        // NOT the combined total which would exceed 600
        $this->assertLessThan(200, $overview['unpaid_total'], 'unpaid_total leaked across tenants');
    }

    /**
     * Create a tenant with one complete pipeline:
     * Customer → Vehicle → WorkOrder → Invoice with line items.
     */
    private function buildTenantWithCompletedWorkOrder(
        string $serviceName,
        float $servicePrice,
        string $technicianName = 'Default Tech',
    ): Tenant {
        $tenant = Tenant::factory()->create();

        $tech = User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $technicianName,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
        ]);

        // Create a completed work order with one service task
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'technician_id' => $tech->id,
            'status' => 'completed',
            'completed_at' => now(),
            'service_tasks' => [
                ['name' => $serviceName, 'price' => $servicePrice, 'completed' => true],
            ],
        ]);

        // Set tenant context so InvoiceService creates the invoice under the right tenant
        app(TenantContext::class)->set($tenant);
        $this->actingAs($tech);

        DB::transaction(function () use ($workOrder) {
            $this->invoiceService->createFromWorkOrder($workOrder);
        });

        return $tenant;
    }
}
