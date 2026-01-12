<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Tire;
use App\Models\Vehicle;
use Illuminate\Support\Collection;

class TireStorageService
{
    /**
     * Get statistics for the dashboard
     */
    public function getStatistics(): array
    {
        return [
            'total_sets' => Tire::stored()->count(),
            'total_tires' => Tire::stored()->sum('quantity'),
            'winter_tires' => Tire::stored()->winterTires()->count(),
            'summer_tires' => Tire::stored()->summerTires()->count(),
            'all_season_tires' => Tire::stored()->allSeasonTires()->count(),
            'storage_utilization' => $this->calculateStorageUtilization(),
            'new_arrivals_month' => Tire::whereMonth('storage_date', now()->month)->count(),
        ];
    }

    /**
     * Calculate storage utilization percentage
     */
    public function calculateStorageUtilization(): int
    {
        $total_capacity = \App\Models\StorageSection::sum('capacity_slots');
        $used_slots = Tire::stored()->sum('quantity');

        if ($total_capacity == 0) {
            return 100;
        }

        return round(($used_slots / $total_capacity) * 100);
    }

    /**
     * Get the visual storage map
     */
    public function getStorageMap(): Collection
    {
        // Explicitly filter by current tenant to avoid Global Scope issues
        $tenantId = auth()->user()->tenant_id ?? tenant()->id;
        $sections = \App\Models\StorageSection::where('tenant_id', $tenantId)->get();

        $map = [];

        foreach ($sections as $section) {
            $used_slots = Tire::byLocation($section->name)->where('status', 'stored')->sum('quantity');
            $total_slots = $section->capacity_slots;

            $map[] = [
                'section' => "Section {$section->name}",
                'used' => $used_slots,
                'total' => $total_slots,
                'percentage' => ($total_slots > 0) ? round(($used_slots / $total_slots) * 100) : 100,
                'color' => $this->getSectionColor($used_slots, $total_slots),
            ];
        }

        return collect($map);
    }

    /**
     * Check if a specific location is available
     */
    public function isLocationAvailable(string $location): bool
    {
        // Check if a tire is currently stored in this location
        return ! Tire::where('storage_location', $location)
            ->where('status', 'stored')
            ->exists();
    }

    /**
     * Get the next available storage location in standard format (e.g. S1-A-01)
     */
    public function getNextAvailableLocation(): ?string
    {
        // Define structure based on UI
        // In a real app, these should preferably come from DB configuration
        $sections = ['S1', 'S2', 'S3', 'S4'];
        $rows = ['A', 'B', 'C', 'D'];
        $slots = range(1, 20);

        // Fetch all occupied locations in one query for performance
        $occupiedLocations = Tire::where('status', 'stored')
            ->pluck('storage_location')
            ->flip()
            ->toArray();

        foreach ($sections as $section) {
            foreach ($rows as $row) {
                foreach ($slots as $slot) {
                    $formattedSlot = str_pad($slot, 2, '0', STR_PAD_LEFT);
                    $location = "{$section}-{$row}-{$formattedSlot}";

                    if (! isset($occupiedLocations[$location])) {
                        return $location;
                    }
                }
            }
        }

        return null; // Full capacity
    }

    /**
     * Assign a new storage location
     * Backward compatibility wrapper
     */
    public function assignStorageLocation(): string
    {
        return $this->getNextAvailableLocation() ?? 'Overflow';
    }

    /**
     * Store new tires for a new customer
     */
    public function storeNewCustomerTires(array $data)
    {
        // Validation should already be done by FormRequest

        // 1. Create or Find Customer
        $customer = $this->findOrCreateCustomer([
            'name' => $data['customer_name'],
            'phone' => $data['customer_phone'] ?? null,
        ]);

        // 2. Create Vehicle
        $vehicle = $this->createVehicle($customer, $data);

        // 3. Store Tires
        $tire = Tire::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'brand' => $data['brand'],
            'model' => $data['model'],
            'size' => $data['size'],
            'season' => $data['season'],
            'quantity' => $data['quantity'],
            'condition' => 'good',
            'storage_location' => $data['storage_location'] ?? $this->assignStorageLocation(),
            'storage_date' => now(),
            'last_inspection_date' => now(),
            'next_inspection_date' => now()->addMonths(6),
            'tread_depth' => 8.0,
            'status' => 'stored',
            'notes' => $data['notes'] ?? null,
        ]);

        return [
            'customer' => $customer,
            'vehicle' => $vehicle,
            'tire' => $tire,
        ];
    }

    // Private helpers

    private function getSectionColor($used, $total)
    {
        if ($total == 0) {
            return 'gray';
        }
        $percentage = ($used / $total) * 100;

        if ($percentage >= 95) {
            return 'red';
        }
        if ($percentage >= 80) {
            return 'yellow';
        }
        if ($percentage >= 60) {
            return 'blue';
        }

        return 'green';
    }

    private function findOrCreateCustomer(array $data)
    {
        // Simple deduplication by phone if exists, otherwise create
        if (! empty($data['phone'])) {
            $customer = Customer::where('phone', $data['phone'])->first();
            if ($customer) {
                // Update name if different (handling name corrections)
                if ($customer->name !== $data['name']) {
                    $customer->update(['name' => $data['name']]);
                }

                return $customer;
            }
        }

        return Customer::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
        ]);
    }

    private function createVehicle(Customer $customer, array $data)
    {
        // Parse vehicle info similar to original controller logic
        $vehicleParts = array_map('trim', explode(',', $data['vehicle_info']));
        $model = $vehicleParts[0] ?? 'Unknown';
        $year = isset($vehicleParts[1]) && is_numeric($vehicleParts[1]) ? (int) $vehicleParts[1] : date('Y');

        $modelParts = explode(' ', $model);
        $make = $modelParts[0] ?? 'Unknown';
        $actualModel = isset($modelParts[1]) ? implode(' ', array_slice($modelParts, 1)) : $model;

        return Vehicle::create([
            'customer_id' => $customer->id,
            'license_plate' => strtoupper(trim($data['registration'])),
            'make' => $make,
            'model' => $actualModel,
            'year' => $year,
        ]);
    }
}
