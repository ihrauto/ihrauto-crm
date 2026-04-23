<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    #[Test]
    public function user_can_view_work_orders_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('work-orders.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function user_can_view_work_order_board()
    {
        $response = $this->actingAs($this->user)
            ->get(route('work-orders.board'));

        $response->assertStatus(200);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function work_order_update_accepts_scheduled_and_waiting_parts_statuses()
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'created',
        ]);

        $this->actingAs($this->user)
            ->put(route('work-orders.update', $workOrder), [
                'status' => 'scheduled',
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertEquals('scheduled', $workOrder->status);

        $this->actingAs($this->user)
            ->put(route('work-orders.update', $workOrder), [
                'status' => 'in_progress',
            ])
            ->assertRedirect();

        $this->actingAs($this->user)
            ->put(route('work-orders.update', $workOrder), [
                'status' => 'waiting_parts',
            ])
            ->assertRedirect();

        $workOrder->refresh();
        $this->assertEquals('waiting_parts', $workOrder->status);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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
