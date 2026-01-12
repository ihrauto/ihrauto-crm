<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or Create some Customers/Vehicles (reusing logic or fetching existing)
        $customer1 = Customer::firstOrCreate(
            ['email' => 'john@example.com'],
            ['name' => 'John Doe', 'phone' => '1234567890', 'address' => '123 Main St']
        );
        $vehicle1 = Vehicle::firstOrCreate(
            ['vin' => 'JT11234567890'],
            ['customer_id' => $customer1->id, 'make' => 'Toyota', 'model' => 'Camry', 'license_plate' => 'KS-01-123-AB']
        );

        $customer2 = Customer::firstOrCreate(
            ['email' => 'sarah@example.com'],
            ['name' => 'Sarah Connor', 'phone' => '9876543210', 'address' => '456 Cyberdyne Ave']
        );
        $vehicle2 = Vehicle::firstOrCreate(
            ['vin' => '1FA6P890123456'],
            ['customer_id' => $customer2->id, 'make' => 'Ford', 'model' => 'Mustang', 'license_plate' => 'KS-02-999-ZZ']
        );

        $date = now(); // Today

        // 1. Scheduled for Today Morning
        Appointment::create([
            'customer_id' => $customer1->id,
            'vehicle_id' => $vehicle1->id,
            'title' => 'Tire Change - Winter',
            'start_time' => $date->copy()->setTime(9, 0),
            'end_time' => $date->copy()->setTime(10, 0),
            'status' => 'scheduled',
            'type' => 'tire_change',
            'notes' => 'Customer is bringing their own tires.',
        ]);

        // 2. Confirmed for Today Afternoon
        Appointment::create([
            'customer_id' => $customer2->id,
            'vehicle_id' => $vehicle2->id,
            'title' => 'Brake Inspection',
            'start_time' => $date->copy()->setTime(14, 0),
            'end_time' => $date->copy()->setTime(15, 0),
            'status' => 'confirmed',
            'type' => 'inspection',
            'notes' => 'Hearing squeaking noise.',
        ]);

        // 3. Tomorrow
        Appointment::create([
            'customer_id' => $customer1->id,
            'vehicle_id' => $vehicle1->id,
            'title' => 'General Service',
            'start_time' => $date->copy()->addDay()->setTime(11, 0),
            'end_time' => $date->copy()->addDay()->setTime(12, 30),
            'status' => 'scheduled',
            'type' => 'service',
        ]);
    }
}
