<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TireHotelTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected Customer $customer;

    protected Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_STANDARD,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->user->assignRole('admin');

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Primary Workshop Customer',
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'license_plate' => 'ZH10001',
        ]);
    }

    #[Test]
    public function user_can_store_new_customer_tires_and_auto_create_a_work_order()
    {
        $response = $this->actingAs($this->user)
            ->post(route('tires-hotel.store'), [
                'customer_name' => 'Mira Tanner',
                'customer_phone' => '+41795550111',
                'vehicle_info' => 'BMW X5, 2021',
                'registration' => 'ZH50001',
                'brand' => 'Michelin',
                'model' => 'Pilot Sport',
                'size' => '225/45R17',
                'season' => 'winter',
                'quantity' => 4,
                'storage_location' => 'S1-A-01',
                'notes' => 'Stored after seasonal swap',
            ]);

        $tire = Tire::latest('id')->first();
        $workOrder = WorkOrder::latest('id')->first();
        $customer = Customer::where('name', 'Mira Tanner')->first();
        $vehicle = Vehicle::where('license_plate', 'ZH50001')->first();

        $response->assertRedirect(route('work-orders.show', $workOrder));
        $this->assertNotNull($tire);
        $this->assertNotNull($workOrder);
        $this->assertNotNull($customer);
        $this->assertNotNull($vehicle);

        $this->assertEquals($customer->id, $tire->customer_id);
        $this->assertEquals($vehicle->id, $tire->vehicle_id);
        $this->assertEquals('stored', $tire->status);
        $this->assertEquals($tire->customer_id, $workOrder->customer_id);
        $this->assertEquals($tire->vehicle_id, $workOrder->vehicle_id);
        $this->assertEquals('created', $workOrder->status);
    }

    #[Test]
    public function season_change_flow_marks_selected_tires_ready_for_pickup()
    {
        $tire = Tire::factory()->stored()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'season' => 'winter',
            'status' => 'stored',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('tires-hotel'))
            ->post(route('tires-hotel.store'), [
                'search_registration' => $this->vehicle->license_plate,
                'from_season' => 'winter',
                'to_season' => 'summer',
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'tire_ids' => (string) $tire->id,
            ]);

        $response->assertRedirect(route('tires-hotel'));
        $tire->refresh();
        $this->assertEquals('ready_pickup', $tire->status);
        $this->assertStringContainsString('[Changed Season]', $tire->notes ?? '');
    }

    #[Test]
    public function search_by_registration_returns_tire_data_for_the_matching_vehicle()
    {
        $tire = Tire::factory()->stored()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'storage_location' => 'S1-B-04',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenant.ajax.tires.search-by-registration', ['registration' => $this->vehicle->license_plate]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('current_tires.0.id', $tire->id)
            ->assertJsonPath('vehicle.id', $this->vehicle->id);
    }

    #[Test]
    public function maintenance_alerts_do_not_leak_other_tenant_records()
    {
        Tire::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'maintenance',
        ]);

        $otherTenant = Tenant::factory()->create([
            'plan' => Tenant::PLAN_STANDARD,
        ]);
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Leaked Customer Name',
        ]);
        $otherVehicle = Vehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        Tire::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
            'status' => 'maintenance',
        ]);

        $response = $this->actingAs($this->user)->get(route('tires-hotel'));

        $response->assertOk();
        $response->assertSee('Primary Workshop Customer');
        $response->assertDontSee('Leaked Customer Name');
    }

    #[Test]
    public function storage_availability_lookup_reports_occupied_slots_and_next_available_slot()
    {
        Tire::factory()->stored()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'storage_location' => 'S1-A-01',
        ]);

        $this->actingAs($this->user)
            ->get(route('tenant.ajax.tires.storage.check', ['location' => 'S1-A-01']))
            ->assertOk()
            ->assertJson([
                'available' => false,
                'location' => 'S1-A-01',
            ]);

        $this->actingAs($this->user)
            ->get(route('tenant.ajax.tires.storage.check'))
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('location', 'S1-A-02');
    }

    #[Test]
    public function tire_hotel_can_generate_a_work_order_for_a_stored_tire()
    {
        $tire = Tire::factory()->stored()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('tires-hotel.generate-work-order', $tire));

        $workOrder = WorkOrder::latest('id')->first();

        $response->assertRedirect(route('work-orders.show', $workOrder));
        $this->assertNotNull($workOrder);
        $this->assertEquals($tire->customer_id, $workOrder->customer_id);
        $this->assertEquals($tire->vehicle_id, $workOrder->vehicle_id);
        $this->assertStringContainsString($tire->storage_location, $workOrder->customer_issues ?? '');
    }
}
