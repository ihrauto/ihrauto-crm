<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for appointment CRUD, model accessors, and tenant isolation.
 */
class AppointmentTest extends TestCase
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
    public function user_can_view_appointments_index()
    {
        $response = $this->actingAs($this->user)
            ->get(route('appointments.index'));

        $response->assertStatus(200);
    }

    #[Test]
    public function user_can_create_appointment()
    {
        $response = $this->actingAs($this->user)
            ->post(route('appointments.store'), [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'start_date' => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'duration' => 60,
                'type' => 'service',
                'title' => 'Oil Change',
                'status' => 'scheduled',
                'notes' => 'Regular maintenance',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Oil Change',
            'type' => 'service',
        ]);
    }

    #[Test]
    public function user_can_update_appointment()
    {
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Oil Change',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('appointments.update', $appointment), [
                'customer_id' => $this->customer->id,
                'start_date' => now()->addDays(2)->toDateString(),
                'start_time' => '14:00',
                'duration' => 120,
                'type' => 'inspection',
                'notes' => 'Updated notes',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'type' => 'inspection',
        ]);
    }

    #[Test]
    public function user_can_update_appointment_status()
    {
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Status Test',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('appointments.update', $appointment), [
                'status' => 'confirmed',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function user_can_delete_appointment()
    {
        $appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'To Delete',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('appointments.destroy', $appointment));

        $response->assertRedirect();
        $this->assertSoftDeleted('appointments', ['id' => $appointment->id]);
    }

    #[Test]
    public function appointment_duration_is_calculated_correctly()
    {
        $appointment = new Appointment([
            'start_time' => now(),
            'end_time' => now()->addMinutes(90),
        ]);

        $this->assertEquals(90, $appointment->duration);
    }

    #[Test]
    public function appointment_status_badge_colors_are_correct()
    {
        $scheduled = new Appointment(['status' => 'scheduled', 'start_time' => now(), 'end_time' => now()->addHour()]);
        $confirmed = new Appointment(['status' => 'confirmed', 'start_time' => now(), 'end_time' => now()->addHour()]);
        $completed = new Appointment(['status' => 'completed', 'start_time' => now(), 'end_time' => now()->addHour()]);
        $cancelled = new Appointment(['status' => 'cancelled', 'start_time' => now(), 'end_time' => now()->addHour()]);

        $this->assertStringContainsString('blue', $scheduled->status_badge_color);
        $this->assertStringContainsString('purple', $confirmed->status_badge_color);
        $this->assertStringContainsString('green', $completed->status_badge_color);
        $this->assertStringContainsString('red', $cancelled->status_badge_color);
    }

    #[Test]
    public function appointment_events_only_returns_own_tenant_records()
    {
        // Create appointment for our tenant
        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Our Appointment',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        // Create appointment for another tenant
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherVehicle = Vehicle::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);
        Appointment::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'vehicle_id' => $otherVehicle->id,
            'title' => 'Other Tenant Appointment',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        // Acting as our user, we should only see our tenant's appointments
        $this->actingAs($this->user);
        $appointments = Appointment::all();
        $this->assertCount(1, $appointments);
        $this->assertEquals($this->tenant->id, $appointments->first()->tenant_id);
    }

    #[Test]
    public function cannot_create_overlapping_appointment_for_same_vehicle()
    {
        // Create an existing appointment for tomorrow 10:00-11:00
        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Existing Appointment',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        // Try to create an overlapping appointment (10:30-11:30)
        $response = $this->actingAs($this->user)
            ->post(route('appointments.store'), [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'start_date' => now()->addDay()->toDateString(),
                'start_time' => '10:30',
                'duration' => 60,
                'type' => 'tire_change',
                'title' => 'Overlapping Appointment',
                'status' => 'scheduled',
            ]);

        $response->assertSessionHasErrors('start_time');
        $this->assertDatabaseMissing('appointments', ['title' => 'Overlapping Appointment']);
    }

    #[Test]
    public function can_create_non_overlapping_appointment_for_same_vehicle()
    {
        // Create an existing appointment for tomorrow 10:00-11:00
        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'vehicle_id' => $this->vehicle->id,
            'title' => 'Morning Appointment',
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
            'type' => 'service',
        ]);

        // Create a non-overlapping appointment (14:00-15:00) — should work
        $response = $this->actingAs($this->user)
            ->post(route('appointments.store'), [
                'customer_id' => $this->customer->id,
                'vehicle_id' => $this->vehicle->id,
                'start_date' => now()->addDay()->toDateString(),
                'start_time' => '14:00',
                'duration' => 60,
                'type' => 'tire_change',
                'title' => 'Afternoon Appointment',
                'status' => 'scheduled',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('appointments', ['title' => 'Afternoon Appointment']);
    }
}
