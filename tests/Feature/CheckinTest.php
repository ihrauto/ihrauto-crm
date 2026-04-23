<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckinTest extends TestCase
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
    public function user_can_view_checkin_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('checkin'));

        $response->assertStatus(200);
    }

    #[Test]
    public function checkin_index_shows_active_checkins()
    {
        $checkin = Checkin::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('checkin'));

        $response->assertStatus(200);
        $response->assertSee($this->customer->name);
    }

    #[Test]
    public function user_can_view_single_checkin()
    {
        $checkin = Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('checkin.show', $checkin));

        $response->assertStatus(200);
    }

    #[Test]
    public function user_can_update_checkin_status()
    {
        $checkin = Checkin::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('checkin.update', $checkin), [
                'status' => 'in_progress',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('checkins', [
            'id' => $checkin->id,
            'status' => 'in_progress',
        ]);
    }

    #[Test]
    public function completing_checkin_sets_checkout_time()
    {
        $checkin = Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'in_progress',
            'checkout_time' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('checkin.update', $checkin), [
                'status' => 'completed',
            ]);

        $response->assertRedirect();
        $checkin->refresh();
        $this->assertEquals('completed', $checkin->status);
        $this->assertNotNull($checkin->checkout_time);
    }

    #[Test]
    public function done_status_maps_to_completed()
    {
        $checkin = Checkin::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('checkin.update', $checkin), [
                'status' => 'done',
            ]);

        $response->assertRedirect();
        $checkin->refresh();
        $this->assertEquals('completed', $checkin->status);
        $this->assertNotNull($checkin->checkout_time);
    }

    #[Test]
    public function checkin_update_validates_status_values()
    {
        $checkin = Checkin::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('checkin.update', $checkin), [
                'status' => 'invalid_status',
            ]);

        $response->assertSessionHasErrors('status');
    }

    #[Test]
    public function checkin_update_rejects_other_tenants_checkin()
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $otherVehicle = Vehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        $otherCheckin = Checkin::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('checkin.update', $otherCheckin), [
                'status' => 'in_progress',
            ]);

        // Should be 404 (tenant scope hides it) or 403
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    #[Test]
    public function checkin_index_seeds_default_bays_if_none_exist()
    {
        // First visit should auto-seed 6 default bays
        $response = $this->actingAs($this->user)
            ->get(route('checkin'));

        $response->assertStatus(200);

        $this->assertDatabaseHas('service_bays', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Bay 1',
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_checkin()
    {
        $response = $this->get(route('checkin'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function existing_vehicle_checkin_creates_work_order_and_persists_uploaded_photos()
    {
        Storage::fake('public');

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Oil Filter',
            'price' => 19.5,
        ]);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'oil_change',
            'category' => 'maintenance',
            'price' => 79,
            'is_active' => true,
        ]);
        $service->products()->attach($product->id, ['quantity' => 2]);

        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'technician',
            'is_active' => true,
        ]);
        $technician->assignRole('technician');

        $response = $this->actingAs($this->user)
            ->post(route('checkin.store'), [
                'form_type' => 'active_user',
                'vehicle_id' => $this->vehicle->id,
                'service_type' => 'oil_change',
                'priority' => 'high',
                'service_bay' => 'Bay 2',
                'service_description' => 'Oil and filter replacement',
                'technician_id' => $technician->id,
                'photos' => [
                    UploadedFile::fake()->image('vehicle-before.jpg'),
                ],
            ]);

        $workOrder = WorkOrder::latest('id')->first();
        $checkin = Checkin::latest('id')->first();
        $photo = WorkOrderPhoto::latest('id')->first();

        $response->assertRedirect(route('work-orders.show', $workOrder));
        $this->assertNotNull($checkin);
        $this->assertNotNull($workOrder);
        $this->assertNotNull($photo);

        $this->assertEquals($checkin->id, $workOrder->checkin_id);
        $this->assertEquals($technician->id, $workOrder->technician_id);
        $this->assertEquals('created', $workOrder->status);
        $this->assertStringContainsString("Check-in #{$checkin->id}", $workOrder->customer_issues ?? '');
        $this->assertEquals(79, $workOrder->service_tasks[0]['price']);
        $this->assertEquals($product->id, $workOrder->parts_used[0]['product_id']);
        $this->assertEquals(2, $workOrder->parts_used[0]['qty']);
        Storage::disk('public')->assertExists($photo->path);
    }

    #[Test]
    public function new_customer_checkin_creates_customer_vehicle_checkin_and_work_order()
    {
        $response = $this->actingAs($this->user)
            ->post(route('checkin.store'), [
                'form_type' => 'new_customer',
                'customer_first_name' => 'Lena',
                'customer_last_name' => 'Meyer',
                'phone' => '+41795550123',
                'email' => 'lena@example.com',
                'street_address' => 'Main Street 10',
                'city' => 'Zurich',
                'postal_code' => '8000',
                'license_plate' => ' zh 12345 ',
                'make' => 'Audi',
                'model' => 'A4',
                'year' => 2022,
                'color' => 'Black',
                'mileage' => 45000,
                'services' => ['oil_change', 'inspection'],
                'priority' => 'medium',
                'service_bay' => 'Bay 1',
                'service_description' => 'Annual service',
            ]);

        $customer = Customer::where('email', 'lena@example.com')->first();
        $vehicle = Vehicle::where('license_plate', 'ZH12345')->first();
        $checkin = Checkin::latest('id')->first();
        $workOrder = WorkOrder::latest('id')->first();

        $response->assertRedirect(route('work-orders.show', $workOrder));
        $this->assertNotNull($customer);
        $this->assertNotNull($vehicle);
        $this->assertNotNull($checkin);
        $this->assertNotNull($workOrder);

        $this->assertEquals($customer->id, $vehicle->customer_id);
        $this->assertEquals($customer->id, $checkin->customer_id);
        $this->assertEquals($vehicle->id, $checkin->vehicle_id);
        $this->assertEquals('oil_change, inspection', $checkin->service_type);
        $this->assertEquals($checkin->id, $workOrder->checkin_id);
    }

    #[Test]
    public function busy_technician_rejection_does_not_create_a_checkin()
    {
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'technician',
            'is_active' => true,
        ]);
        $technician->assignRole('technician');

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'technician_id' => $technician->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('checkin'))
            ->post(route('checkin.store'), [
                'form_type' => 'active_user',
                'vehicle_id' => $this->vehicle->id,
                'service_type' => 'repair',
                'priority' => 'urgent',
                'service_bay' => 'Bay 4',
                'technician_id' => $technician->id,
            ]);

        $response->assertRedirect(route('checkin'));
        $response->assertSessionHas('error', 'Selected technician is currently busy with another job.');
        $this->assertSame(0, Checkin::count());
        $this->assertSame(1, WorkOrder::count());
    }

    #[Test]
    public function checkin_store_is_rate_limited_after_ten_requests_per_minute()
    {
        $payload = [
            'form_type' => 'active_user',
            'vehicle_id' => $this->vehicle->id,
            'service_type' => 'maintenance',
            'priority' => 'medium',
            'service_bay' => 'Bay 3',
        ];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->actingAs($this->user)->post(route('checkin.store'), $payload);
            $this->assertNotEquals(429, $response->status(), "Attempt {$attempt} should not be throttled.");
        }

        $this->actingAs($this->user)
            ->post(route('checkin.store'), $payload)
            ->assertStatus(429);
    }

    #[Test]
    public function failed_photo_storage_rolls_back_the_checkin_and_work_order()
    {
        $disk = Mockery::mock();
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        $response = $this->actingAs($this->user)
            ->from(route('checkin'))
            ->post(route('checkin.store'), [
                'form_type' => 'active_user',
                'vehicle_id' => $this->vehicle->id,
                'service_type' => 'oil_change',
                'priority' => 'medium',
                'service_bay' => 'Bay 1',
                'photos' => [
                    UploadedFile::fake()->image('vehicle-before.jpg'),
                ],
            ]);

        $response->assertRedirect(route('checkin'));
        $response->assertSessionHas('error');
        $this->assertSame(0, Checkin::count());
        $this->assertSame(0, WorkOrder::count());
        $this->assertSame(0, WorkOrderPhoto::count());
    }
}
