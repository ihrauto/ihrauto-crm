<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Tenant $tenant;
    protected Customer $customer;
    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    /** @test */
    public function user_can_view_work_orders_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('work-orders.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_view_work_order_board()
    {
        $response = $this->actingAs($this->user)
            ->get(route('work-orders.board'));

        $response->assertStatus(200);
    }

    /** @test */
    public function user_can_view_single_work_order()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'created',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('work-orders.show', $workOrder));

        $response->assertStatus(200);
    }

    /** @test */
    public function work_order_status_label_is_correct()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'in_progress',
        ]);

        $this->assertEquals('In Progress', $workOrder->status_label);
    }

    /** @test */
    public function work_order_status_badge_color_is_correct()
    {
        $createdOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'created',
        ]);

        $completedOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'completed',
        ]);

        $this->assertStringContainsString('gray', $createdOrder->status_badge_color);
        $this->assertStringContainsString('green', $completedOrder->status_badge_color);
    }

    /** @test */
    public function work_order_can_have_service_tasks_as_array()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'service_tasks' => [
                ['name' => 'Oil Change', 'price' => 50],
                ['name' => 'Brake Inspection', 'price' => 30],
            ],
        ]);

        $this->assertIsArray($workOrder->service_tasks);
        $this->assertCount(2, $workOrder->service_tasks);
        $this->assertEquals('Oil Change', $workOrder->service_tasks[0]['name']);
    }

    /** @test */
    public function work_order_can_have_parts_used_as_array()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'parts_used' => [
                ['name' => 'Oil Filter', 'qty' => 1, 'price' => 15],
                ['name' => 'Brake Pad', 'qty' => 2, 'price' => 45],
            ],
        ]);

        $this->assertIsArray($workOrder->parts_used);
        $this->assertCount(2, $workOrder->parts_used);
    }

    /** @test */
    public function work_orders_are_tenant_isolated()
    {
        // Create work order for our tenant
        $ourWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        // Create work order for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $otherVehicle = Vehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $theirWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        // User should not be able to view other tenant's work order
        // Returns 404 because tenant scope hides it
        $response = $this->actingAs($this->user)
            ->get(route('work-orders.show', $theirWorkOrder));

        $response->assertNotFound();
    }

    /** @test */
    public function work_order_belongs_to_customer()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertInstanceOf(Customer::class, $workOrder->customer);
        $this->assertEquals($this->customer->id, $workOrder->customer->id);
    }

    /** @test */
    public function work_order_belongs_to_vehicle()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertInstanceOf(Vehicle::class, $workOrder->vehicle);
        $this->assertEquals($this->vehicle->id, $workOrder->vehicle->id);
    }

    /** @test */
    public function work_order_can_have_technician_assigned()
    {
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'technician_id' => $technician->id,
        ]);

        $this->assertInstanceOf(User::class, $workOrder->technician);
        $this->assertEquals($technician->id, $workOrder->technician->id);
    }
}
