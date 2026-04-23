<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     *
     * Customer deduplication cascade:
     *   1. Try to find by normalized phone number (strongest signal — phone
     *      numbers are usually per-person and entered consistently).
     *   2. If no phone match, fall back to email lookup (case-insensitive).
     *      Email dedup catches the case where a returning customer gives a
     *      new number (e.g. new SIM) but the same email.
     *   3. If neither matches, create a new customer.
     *
     * All lookups are tenant-scoped automatically via BelongsToTenant.
     */
    public function createWithNewRegistration(array $data)
    {
        $customer = $this->findExistingCustomerByPhone($data['phone'] ?? null)
            ?? $this->findExistingCustomerByEmail($data['email'] ?? null);

        if (! $customer) {
            $customer = $this->createCustomer($data);
        }

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

    private function findExistingCustomerByPhone(?string $phone): ?Customer
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', trim($phone));

        return Customer::whereRaw("REPLACE(phone, ' ', '') = ?", [$normalized])->first();
    }

    /**
     * Find a customer by email (case-insensitive). Returns null when no email
     * was provided, or the email is whitespace-only, to avoid matching records
     * that also have a null email.
     */
    private function findExistingCustomerByEmail(?string $email): ?Customer
    {
        if (! $email) {
            return null;
        }

        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return Customer::whereRaw('LOWER(email) = ?', [$normalized])->first();
    }

    private function createCustomer(array $data)
    {
        \App\Support\PlanQuota::assertCanAddCustomer();

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

    private function findVehicle(string $licensePlate)
    {
        [$expr, $bindings] = \App\Support\LicensePlate::whereExpression($licensePlate);

        return Vehicle::whereRaw($expr, $bindings)->first();
    }

    private function createVehicle(Customer $customer, array $data)
    {
        \App\Support\PlanQuota::assertCanAddVehicle();

        return Vehicle::create([
            'customer_id' => $customer->id,
            'license_plate' => \App\Support\LicensePlate::normalize($data['license_plate']),
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'color' => $data['color'],
            'mileage' => $data['mileage'] ?? null,
        ]);
    }

    /**
     * Build service tasks and parts arrays from a comma-separated service type string.
     *
     * @return array{tasks: array, parts: array}
     */
    public function resolveServiceTasksAndParts(?string $serviceType): array
    {
        $tasks = [];
        $parts = [];

        if (empty($serviceType)) {
            return compact('tasks', 'parts');
        }

        $serviceNames = explode(',', $serviceType);
        foreach ($serviceNames as $name) {
            $name = trim($name);
            if (empty($name)) {
                continue;
            }

            $service = \App\Models\Service::with('products')
                ->where('name', $name)
                ->orWhere('name', str_replace('_', ' ', $name))
                ->first();

            $tasks[] = [
                'name' => $service ? $service->name : ucfirst(str_replace('_', ' ', $name)),
                'completed' => false,
                'price' => $service ? $service->price : 0,
            ];

            if ($service && $service->products->isNotEmpty()) {
                foreach ($service->products as $product) {
                    $parts[] = [
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'qty' => $product->pivot->quantity,
                        'price' => $product->price,
                    ];
                }
            }
        }

        return compact('tasks', 'parts');
    }

    /**
     * Create a work order from a checkin record.
     */
    public function createWorkOrderFromCheckin(Checkin $checkin, ?int $technicianId = null): WorkOrder
    {
        // B-01: gate on plan quota before inserting — BASIC plan has a monthly
        // work-order ceiling that must be enforced server-side, not just in UI.
        \App\Support\PlanQuota::assertCanCreateWorkOrder();

        $resolved = $this->resolveServiceTasksAndParts($checkin->service_type);

        return WorkOrder::create([
            'tenant_id' => tenant_id(),
            'checkin_id' => $checkin->id,
            'customer_id' => $checkin->customer_id,
            'vehicle_id' => $checkin->vehicle_id,
            'technician_id' => $technicianId,
            'status' => 'created',
            'customer_issues' => "Auto-created from Check-in #{$checkin->id} - Service: ".($checkin->service_type ?? 'General'),
            'service_tasks' => $resolved['tasks'],
            'parts_used' => $resolved['parts'],
        ]);
    }

    /**
     * Upload photos and attach them to a work order.
     *
     * @param  UploadedFile[]  $photos
     * @return string[] Paths of stored files (for rollback on error).
     */
    public function uploadPhotos(WorkOrder $workOrder, array $photos): array
    {
        $storedPaths = [];

        foreach ($photos as $photo) {
            if (@getimagesize($photo->getRealPath()) === false) {
                continue;
            }

            $filename = Str::uuid().'.'.$photo->getClientOriginalExtension();
            $path = "work-order-photos/{$workOrder->tenant_id}/{$workOrder->id}/{$filename}";

            if (! Storage::disk('public')->put($path, file_get_contents($photo))) {
                throw new \RuntimeException('Failed to store check-in photo.');
            }
            $storedPaths[] = $path;

            WorkOrderPhoto::create([
                'tenant_id' => $workOrder->tenant_id,
                'work_order_id' => $workOrder->id,
                'user_id' => auth()->id(),
                'filename' => $filename,
                'original_name' => $photo->getClientOriginalName(),
                'path' => $path,
                'type' => 'before',
                'caption' => 'Uploaded during check-in',
            ]);
        }

        return $storedPaths;
    }

    /**
     * Clean up uploaded photos on failure.
     */
    public function cleanupPhotos(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
