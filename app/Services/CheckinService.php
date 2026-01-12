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
        // 1. Find or Create Customer
        $customer = $this->findCustomer($data);

        if ($customer) {
            // Restore if trashed
            if ($customer->trashed()) {
                $customer->restore();
            }
        } else {
            $customer = $this->createCustomer($data);
        }

        // 2. Update Customer if found (enforce fresh data)
        if (! $customer->wasRecentlyCreated) {
            $this->updateCustomer($customer, $data);
        }

        // 3. Find or Create Vehicle
        $vehicle = $this->findVehicle($data['license_plate']) ?? $this->createVehicle($customer, $data);

        // 4. Update Vehicle if found
        if (! $vehicle->wasRecentlyCreated) {
            $vehicle->update([
                'customer_id' => $customer->id,
                'mileage' => $data['mileage'] ?? $vehicle->mileage,
            ]);
        }

        // 5. Create Check-in
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

    private function findCustomer(array $data)
    {
        if (! empty($data['email'])) {
            $email = trim($data['email']);
            // Search withTrashed to prevent unique constraint violations on soft-deleted records
            $customer = Customer::withTrashed()
                ->where('email', $email)
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        if (! empty($data['phone'])) {
            $phone = trim($data['phone']);

            return Customer::withTrashed()
                ->where('phone', $phone)
                ->first();
        }

        return null;
    }

    private function createCustomer(array $data)
    {
        $addressParts = array_filter([
            $data['street_address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
        ]);

        return Customer::create([
            'name' => trim($data['customer_first_name'].' '.$data['customer_last_name']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'email' => isset($data['email']) ? trim($data['email']) : null,
            'address' => implode(', ', $addressParts),
        ]);
    }

    private function updateCustomer(Customer $customer, array $data)
    {
        $addressParts = array_filter([
            $data['street_address'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
        ]);

        $customer->update([
            'name' => trim($data['customer_first_name'].' '.$data['customer_last_name']),
            'phone' => isset($data['phone']) ? trim($data['phone']) : $customer->phone,
            // Don't update email if it conflicts? Or assume findCustomer handled it?
            // If we found by phone, but email is different and exists elsewhere... edge case.
            // safely update email only if provided
            'email' => isset($data['email']) ? trim($data['email']) : $customer->email,
            'address' => implode(', ', $addressParts) ?: $customer->address,
        ]);
    }

    private function findVehicle(string $licensePlate)
    {
        $normalized = strtoupper(str_replace(' ', '', trim($licensePlate)));

        return Vehicle::whereRaw('UPPER(REPLACE(license_plate, " ", "")) = ?', [$normalized])->first();
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
