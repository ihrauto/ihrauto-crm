<?php

namespace Tests\Unit\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\ServiceBay;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CheckinService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected CheckinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->user->assignRole('admin');
        $this->actingAs($this->user);

        $this->service = app(CheckinService::class);

        ServiceBay::create(['tenant_id' => $this->tenant->id, 'name' => 'Bay 1', 'is_active' => true, 'sort_order' => 1]);
    }

    private function newRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'phone' => '+41791234567',
            'email' => 'john@example.com',
            'street_address' => 'Bahnhofstrasse 1',
            'city' => 'Zurich',
            'postal_code' => '8001',
            'license_plate' => 'ZH 12345',
            'make' => 'BMW',
            'model' => '320i',
            'year' => 2020,
            'color' => 'Black',
            'mileage' => 50000,
            'services' => ['oil_change', 'tire_change'],
            'service_description' => 'Full service',
            'priority' => 'high',
            'service_bay' => 'Bay 1',
        ], $overrides);
    }

    #[Test]
    public function it_creates_checkin_for_existing_vehicle()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $vehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $checkin = $this->service->createForExistingVehicle([
            'vehicle_id' => $vehicle->id,
            'service_type' => 'oil_change',
            'service_description' => 'Regular oil change',
            'priority' => 'medium',
            'service_bay' => 'Bay 1',
        ]);

        $this->assertInstanceOf(Checkin::class, $checkin);
        $this->assertEquals($customer->id, $checkin->customer_id);
        $this->assertEquals($vehicle->id, $checkin->vehicle_id);
        $this->assertEquals('Bay 1', $checkin->service_bay);
    }

    #[Test]
    public function it_creates_new_customer_and_vehicle_for_new_registration()
    {
        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData());

        $this->assertInstanceOf(Checkin::class, $checkin);
        // DATA-03: `phone` is encrypted at rest — `assertDatabaseHas` on
        // the plaintext no longer matches. Assert by name + deterministic
        // phone_hash sidecar, then verify the decrypted attribute.
        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'phone_hash' => Customer::lookupPhoneHash('+41791234567'),
        ]);
        $this->assertSame('+41791234567', Customer::where('name', 'John Doe')->first()->phone);
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ZH12345', 'make' => 'BMW']);
    }

    #[Test]
    public function it_deduplicates_customer_by_phone_number()
    {
        $existing = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Customer',
            'phone' => '+41791234567',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'customer_first_name' => 'Different',
            'customer_last_name' => 'Name',
            'phone' => '+41 79 123 4567', // Same number with spaces
            'license_plate' => 'ZH 99999',
        ]));

        // Should reuse existing customer
        $this->assertEquals($existing->id, $checkin->customer_id);
    }

    #[Test]
    public function it_creates_new_customer_when_phone_does_not_match()
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41791111111',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'phone' => '+41792222222',
            'license_plate' => 'ZH 88888',
        ]));

        $this->assertEquals(2, Customer::count());
    }

    #[Test]
    public function it_reuses_existing_vehicle_by_license_plate()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $existingVehicle = Vehicle::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'license_plate' => 'BE54321',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'phone' => '+41799999999',
            'license_plate' => 'BE 54321', // Same plate with space
        ]));

        $this->assertEquals($existingVehicle->id, $checkin->vehicle_id);
        $this->assertEquals(1, Vehicle::where('license_plate', 'BE54321')->count());
    }

    #[Test]
    public function it_deduplicates_customer_by_email_when_phone_does_not_match(): void
    {
        $existing = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Existing Customer',
            'phone' => '+41791111111', // different phone
            'email' => 'john@example.com',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'customer_first_name' => 'Different',
            'customer_last_name' => 'Name',
            'phone' => '+41799999999', // completely different phone
            'email' => 'John@Example.com', // case-insensitive email match
            'license_plate' => 'ZH 77777',
        ]));

        // Should reuse existing customer via email fallback
        $this->assertEquals($existing->id, $checkin->customer_id);
        $this->assertEquals(1, Customer::count());
    }

    #[Test]
    public function phone_match_takes_precedence_over_email(): void
    {
        $phoneMatch = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41791234567',
            'email' => 'phone-customer@example.com',
        ]);

        $emailMatch = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41799999999',
            'email' => 'email-customer@example.com',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'phone' => '+41 79 123 4567', // matches phoneMatch
            'email' => 'email-customer@example.com', // also matches emailMatch
            'license_plate' => 'ZH 66666',
        ]));

        // Phone match wins
        $this->assertEquals($phoneMatch->id, $checkin->customer_id);
    }

    #[Test]
    public function it_creates_new_customer_when_neither_phone_nor_email_matches(): void
    {
        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+41791111111',
            'email' => 'existing@example.com',
        ]);

        $checkin = $this->service->createWithNewRegistration($this->newRegistrationData([
            'phone' => '+41792222222',
            'email' => 'new@example.com',
            'license_plate' => 'ZH 55555',
        ]));

        $this->assertEquals(2, Customer::count());
    }
}
