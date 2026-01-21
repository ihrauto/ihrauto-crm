<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Vehicle;

class CheckinService
{
    /**
     * Create a check-in for an existing vehicle.
     */
    public function createForExistingVehicle(array $data)
    {
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        return Checkin::create([
            'customer_id' => $vehicle->customer_id,
            'vehicle_id' => $vehicle->id,
            'service_type' => $data['service_type'],
            'service_description' => $data['service_description'] ?? null,
            'priority' => $data['priority'],
            'checkin_time' => now(),
            'service_bay' => $data['service_bay'],
        ]);
    }

    /**
     * Register a new customer/vehicle and create a check-in.
     */
    public function createWithNewRegistration(array $data)
    {
        // 1. Always create a NEW customer when using "Add New Client" flow
        // This ensures new customers are never replaced/merged with existing ones
        $customer = $this->createCustomer($data);

        // 2. Find existing vehicle by license plate, or create new one
        $vehicle = $this->findVehicle($data['license_plate']);

        if ($vehicle) {
            // Update vehicle to link to new customer
            $vehicle->update([
                'customer_id' => $customer->id,
                'mileage' => $data['mileage'] ?? $vehicle->mileage,
            ]);
        } else {
            $vehicle = $this->createVehicle($customer, $data);
        }

        // 3. Create Check-in
        return Checkin::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type' => is_array($data['services']) ? implode(', ', $data['services']) : $data['services'],
            'service_description' => $data['service_description'] ?? null,
            'priority' => $data['priority'],
            'checkin_time' => now(),
            'service_bay' => $data['service_bay'],
        ]);
    }

    private function createCustomer(array $data)
    {
        $addressParts = array_filter([
            $data['street_address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
        ]);

        return Customer::create([
            'name' => trim($data['customer_first_name'] . ' ' . $data['customer_last_name']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'email' => isset($data['email']) ? trim($data['email']) : null,
            'address' => implode(', ', $addressParts),
        ]);
    }

    private function findVehicle(string $licensePlate)
    {
        $normalized = strtoupper(str_replace(' ', '', trim($licensePlate)));

        return Vehicle::whereRaw("UPPER(REPLACE(license_plate, ' ', '')) = ?", [$normalized])->first();
    }

    private function createVehicle(Customer $customer, array $data)
    {
        return Vehicle::create([
            'customer_id' => $customer->id,
            'license_plate' => strtoupper(str_replace(' ', '', trim($data['license_plate']))),
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'color' => $data['color'],
            'mileage' => $data['mileage'] ?? null,
        ]);
    }
}
