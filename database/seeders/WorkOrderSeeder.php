<?php

namespace Database\Seeders;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;

class WorkOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we have a technician (User)
        $tech = User::first();
        if (! $tech) {
            $tech = User::create([
                'name' => 'Demo Technician',
                'email' => 'tech@ihrauto.com',
                'password' => bcrypt('password'),
            ]);
        }

        // Scenario 1: New Job (Created) - Oil Change
        $customer1 = $this->getOrCreateCustomer('john@example.com', [
            'name' => 'John Doe',
            'phone' => '1234567890',
            'address' => '123 Main St',
        ]);

        $vehicle1 = $this->getOrCreateVehicle('JT11234567890', [
            'customer_id' => $customer1->id,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'license_plate' => 'KS-01-123-AB',
        ]);

        $checkin1 = Checkin::create([
            'customer_id' => $customer1->id,
            'vehicle_id' => $vehicle1->id,
            'service_type' => 'oil_change',
            'service_description' => 'Regular oil change requested. Customer mentioned slight squeak.',
            'priority' => 'medium',
            'service_bay' => 'Bay 1',
            'status' => 'pending',
            'checkin_time' => now()->subHours(1),
        ]);

        WorkOrder::create([
            'checkin_id' => $checkin1->id,
            'customer_id' => $customer1->id,
            'vehicle_id' => $vehicle1->id,
            'status' => 'created',
            'customer_issues' => $checkin1->service_description,
            'service_tasks' => [
                ['name' => 'Oil Change 5W-30', 'completed' => false],
                ['name' => 'Replace Oil Filter', 'completed' => false],
                ['name' => 'Check Brake Fluid', 'completed' => false],
            ],
            'technician_id' => $tech->id,
        ]);

        // Scenario 2: In Progress - Braking Issue
        $customer2 = $this->getOrCreateCustomer('sarah@example.com', [
            'name' => 'Sarah Connor',
            'phone' => '9876543210',
            'address' => '456 Cyberdyne Ave',
        ]);

        $vehicle2 = $this->getOrCreateVehicle('1FA6P890123456', [
            'customer_id' => $customer2->id,
            'make' => 'Ford',
            'model' => 'Mustang',
            'year' => 2018,
            'license_plate' => 'KS-02-999-ZZ',
        ]);

        $checkin2 = Checkin::create([
            'customer_id' => $customer2->id,
            'vehicle_id' => $vehicle2->id,
            'service_type' => 'repair',
            'service_description' => 'Grinding noise when braking.',
            'priority' => 'high',
            'service_bay' => 'Bay 3',
            'status' => 'in_progress',
            'checkin_time' => now()->subHours(4),
        ]);

        WorkOrder::create([
            'checkin_id' => $checkin2->id,
            'customer_id' => $customer2->id,
            'vehicle_id' => $vehicle2->id,
            'status' => 'in_progress',
            'customer_issues' => $checkin2->service_description,
            'service_tasks' => [
                ['name' => 'Inspect Brake Pads', 'completed' => true],
                ['name' => 'Inspect Rotors', 'completed' => true],
                ['name' => 'Replace Front Pads', 'completed' => false],
            ],
            'technician_notes' => 'Confirmed grinding noise. Front pads are completely worn down. Rotors look okay but need resurfacing.',
            'parts_used' => [
                ['name' => 'Ceramic Brake Pads (Front)', 'qty' => 1],
                ['name' => 'Brake Cleaner', 'qty' => 1],
            ],
            'started_at' => now()->subHours(3),
            'technician_id' => $tech->id,
        ]);

        // Scenario 3: Completed - Tire Swap
        $customer3 = $this->getOrCreateCustomer('mike@example.com', [
            'name' => 'Mike Ross',
            'phone' => '5551234567',
            'address' => '789 Pearson Specter',
        ]);

        $vehicle3 = $this->getOrCreateVehicle('WBA123456789012', [
            'customer_id' => $customer3->id,
            'make' => 'BMW',
            'model' => 'X5',
            'year' => 2022,
            'license_plate' => 'KS-05-555-AA',
        ]);

        $checkin3 = Checkin::create([
            'customer_id' => $customer3->id,
            'vehicle_id' => $vehicle3->id,
            'service_type' => 'tire_change',
            'service_description' => 'Swap to winter tires.',
            'priority' => 'low',
            'service_bay' => 'Bay 2',
            'status' => 'completed',
            'checkin_time' => now()->subDays(1)->hour(9),
            'checkout_time' => now()->subDays(1)->hour(11),
        ]);

        WorkOrder::create([
            'checkin_id' => $checkin3->id,
            'customer_id' => $customer3->id,
            'vehicle_id' => $vehicle3->id,
            'status' => 'completed',
            'customer_issues' => $checkin3->service_description,
            'service_tasks' => [
                ['name' => 'Mount Winter Tires', 'completed' => true],
                ['name' => 'Balance Wheels', 'completed' => true],
                ['name' => 'Check Tire Pressure', 'completed' => true],
            ],
            'technician_notes' => 'Tires swapped successfully. Summer tires bagged and placed in trunk.',
            'parts_used' => [
                ['name' => 'Wheel Weights (5g)', 'qty' => 8],
                ['name' => 'Tire Bags', 'qty' => 4],
            ],
            'started_at' => now()->subDays(1)->hour(9)->minute(15),
            'completed_at' => now()->subDays(1)->hour(10)->minute(45),
            'technician_id' => $tech->id,
        ]);
    }

    private function getOrCreateCustomer($email, $data)
    {
        $customer = Customer::withTrashed()->where('email', $email)->first();
        if ($customer) {
            if ($customer->trashed()) {
                $customer->restore();
            }

            return $customer;
        }

        return Customer::create(array_merge(['email' => $email], $data));
    }

    private function getOrCreateVehicle($vin, $data)
    {
        $vehicle = Vehicle::withTrashed()->where('vin', $vin)->first();
        if ($vehicle) {
            if ($vehicle->trashed()) {
                $vehicle->restore();
            }

            return $vehicle;
        }

        return Vehicle::create(array_merge(['vin' => $vin], $data));
    }
}
