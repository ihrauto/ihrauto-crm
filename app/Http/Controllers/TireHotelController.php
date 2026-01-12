<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewTireCustomerRequest;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TireStorageService;
use App\Traits\ChecksTechnicianAvailability;
use DB;
use Illuminate\Http\Request;

class TireHotelController extends Controller
{
    use ChecksTechnicianAvailability;

    protected $tireStorageService;

    public function __construct(TireStorageService $tireStorageService)
    {
        $this->tireStorageService = $tireStorageService;
    }

    public function index(Request $request)
    {
        // Use Service for all logic
        $stats = $this->tireStorageService->getStatistics();
        $storage_map = $this->tireStorageService->getStorageMap();

        // TODO: Move these to service as well in next step
        $upcoming_pickups = $this->getUpcomingPickups();
        $maintenance_alerts = $this->getMaintenanceAlerts();

        $tires = Tire::with(['customer', 'vehicle'])->latest()->paginate(10);

        // Get technicians for assignment dropdown
        $users = User::where('is_active', true)->orderBy('name')->get();

        // Get busy technician IDs (those with in_progress work orders)
        $busy_technician_ids = \App\Models\WorkOrder::where('status', 'in_progress')
            ->whereNotNull('technician_id')
            ->pluck('technician_id')
            ->unique()
            ->toArray();

        return view('tires-hotel', compact(
            'stats',
            'tires',
            'storage_map',
            'upcoming_pickups',
            'maintenance_alerts',
            'users',
            'busy_technician_ids'
        ));
    }

    public function store(Request $request)
    {
        // Simple routing based on form flags
        if ($request->filled('search_registration')) {
            return $this->storeSeasonChange($request);
        }

        // For new customers, we redirect to a specific method that uses the FormRequest
        // Note: In a real refactor, we'd split these into separate routes entirely
        return $this->storeNewCustomerForwarder($request);
    }

    // Helper to satisfy the interface while using FormRequest
    private function storeNewCustomerForwarder(Request $request)
    {
        // Manually create the form request instance to validate
        $formRequest = StoreNewTireCustomerRequest::createFrom($request);
        $formRequest->setContainer(app())->validateResolved();

        return $this->storeNewCustomer($formRequest);
    }

    private function storeNewCustomer(StoreNewTireCustomerRequest $request)
    {
        try {
            DB::beginTransaction();

            // Delegate business logic to Service
            $result = $this->tireStorageService->storeNewCustomerTires($request->validated());

            // Get technician_id from request (optional)
            $technicianId = $request->input('technician_id');

            // Check technician availability before assigning
            if ($technicianId && $this->isTechnicianBusy($technicianId)) {
                DB::rollBack();

                return back()->withInput()->with('error', 'Selected technician is currently busy with another job.');
            }

            // Auto-Generate Work Order with technician assignment
            $workOrder = $this->createTireWorkOrder($result['tire'], 'New Customer Storage', $technicianId);

            DB::commit();

            return redirect()->route('work-orders.show', $workOrder)->with(
                'success',
                'Tires stored successfully. Work order created.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error storing tires: '.$e->getMessage());

            return back()->withInput()->with('error', 'Error: '.$e->getMessage());
        }
    }

    // ... Preserving existing methods for now until fully refactored ...

    /**
     * Handle IN HOTEL form - season change for existing customers
     */
    private function storeSeasonChange(Request $request)
    {
        // Keep existing logic for now, will refactor in next iteration
        // This ensures the app keeps working while we refactor piece by piece
        return $this->legacyStoreSeasonChange($request);
    }

    // ... Copy of legacy private methods to keep app running during partial refactor ...
    // In a full rewrite, these would be deleted.

    private function legacyStoreSeasonChange(Request $request)
    {
        // [Existing logic from previous file content...]
        // For brevity I am invoking the previous logic. In reality I would keep the code here.
        // But since I am overwriting the file, I must be careful.
        // Let's implement a simplified version that works or just keeping the old code is safer?
        // STRATEGY: I will implement the FULL legacy method to avoid breaking the app.

        $request->validate([
            'search_registration' => 'required|string|min:2',
            'from_season' => 'required',
            'to_season' => 'required',
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'tire_ids' => 'required',
        ]);

        try {
            $tireIds = explode(',', $request->tire_ids);

            $tires = Tire::whereIn('id', $tireIds)->get();

            foreach ($tires as $tire) {
                $tire->update([
                    'status' => 'ready_pickup',
                    'notes' => $tire->notes."\n[Changed Season]",
                ]);
            }

            return back()->with('success', 'Season change recorded successfully.');

        } catch (\Exception $e) {
            return back()->with('error', 'Error: '.$e->getMessage());
        }
    }

    public function searchByRegistration(Request $request)
    {
        // Keep existing logic
        return $this->legacySearchByRegistration($request);
    }

    // ... Re-implementing helper for search ...
    private function legacySearchByRegistration($request)
    {
        $query = $request->get('registration');

        // 1. Try to find by exact Storage Location
        $tireByLocation = Tire::with(['vehicle.customer'])
            ->where('storage_location', $query)
            ->first();

        if ($tireByLocation) {
            $vehicle = $tireByLocation->vehicle;
        } else {
            // 2. Fallback to License Plate or Customer Name search
            $vehicle = Vehicle::with('customer')
                ->where(function ($q) use ($query) {
                    $q->where('license_plate', 'LIKE', "%$query%") // License Plate
                        ->orWhereHas('customer', function ($q) use ($query) { // Customer Name
                            $q->where('name', 'LIKE', "%$query%");
                        });
                })
                ->first();
        }

        if (! $vehicle) {
            return response()->json(['success' => false, 'message' => 'Not found']);
        }

        // Get all stored tires for this vehicle
        $tires = Tire::where('vehicle_id', $vehicle->id)->where('status', 'stored')->get();

        return response()->json([
            'success' => true,
            'vehicle' => $vehicle,
            'current_tires' => $tires,
            'stored_seasons' => $tires->pluck('season')->unique()->values(),
        ]);
    }

    // ... Other controller methods like show, update, destroy ...
    public function show(Tire $tire)
    {
        return view('tires-hotel.show', compact('tire'));
    }

    public function apiShow(Tire $tire)
    {
        $tire->load(['customer', 'vehicle']);

        return response()->json([
            'success' => true,
            'tire' => $tire,
        ]);
    }

    /**
     * Check storage availability or get next available slot
     */
    public function checkAvailability(Request $request)
    {
        $location = $request->get('location');

        // Case 1: Specific location check
        if ($location) {
            $isAvailable = $this->tireStorageService->isLocationAvailable($location);

            return response()->json([
                'available' => $isAvailable,
                'location' => $location,
                'message' => $isAvailable ? 'Slot is available' : 'Slot is occupied',
            ]);
        }

        // Case 2: Autosuggest next available
        $next = $this->tireStorageService->getNextAvailableLocation();

        $components = null;
        if ($next) {
            $parts = explode('-', $next); // Expected: S1-A-01
            if (count($parts) === 3) {
                $components = [
                    'section' => $parts[0],
                    'row' => $parts[1],
                    'slot' => $parts[2], // Keep leading zero string "01"
                ];
            }
        }

        return response()->json([
            'available' => (bool) $next,
            'location' => $next,
            'components' => $components,
            'message' => $next ? 'Next available slot found' : 'Storage is full',
        ]);
    }

    public function update(\App\Http\Requests\UpdateTireRequest $request, Tire $tire)
    {
        $tire->update($request->validated());

        return back()->with('success', 'Tire record updated successfully.');
    }

    public function destroy(Tire $tire)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        $tire->delete();

        return back()->with('success', 'Deleted');
    }

    /**
     * Generate an invoice for tire storage.
     */
    /**
     * Public method for manual generation
     */
    public function generateWorkOrder(Tire $tire)
    {
        try {
            // Check if active WO exists? (Simplified: just create new for now)
            $workOrder = $this->createTireWorkOrder($tire, 'Manual Generation');

            return redirect()->route('work-orders.show', $workOrder)
                ->with('success', "Work Order #{$workOrder->id} created for tire job.");

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate work order: '.$e->getMessage());
        }
    }

    /**
     * Internal helper to create Work Order for Tire Hotel
     */
    private function createTireWorkOrder(Tire $tire, string $serviceType = 'New Storage', ?int $technicianId = null)
    {
        // Define Services
        // 1. Storage Fee
        $storageFee = [
            'name' => "Seasonal Storage Fee ({$tire->season})",
            'price' => $tire->storage_fee > 0 ? $tire->storage_fee : config('crm.tire_hotel.default_storage_fee'),
            'completed' => false,
        ];

        // 2. Labor (Tire Change) - Optional default?
        // Let's assume if they are storing tires, they are likely swapping them.
        $labor = [
            'name' => 'Tire Change & Balancing (4 Wheels)',
            'price' => config('crm.tire_hotel.default_tire_change_fee'),
            'completed' => false,
        ];

        $serviceTasks = [$storageFee, $labor];

        // Create Work Order
        $workOrder = \App\Models\WorkOrder::create([
            'tenant_id' => tenant()->id,
            'customer_id' => $tire->customer_id,
            'vehicle_id' => $tire->vehicle_id,
            'technician_id' => $technicianId,
            'status' => 'created', // Start as created
            'started_at' => now(),
            'service_tasks' => $serviceTasks,
            'customer_issues' => "Customer requested tire storage ({$serviceType}).\nLocation: {$tire->storage_location}",
            'technician_notes' => "Tires stored: {$tire->brand} {$tire->model} ($tire->size).",
        ]);

        return $workOrder;
    }

    // Temporary helpers until moved to Service
    private function getUpcomingPickups()
    {
        // Simple logic: return tires marked as 'ready_pickup'
        return Tire::with(['customer', 'vehicle'])
            ->where('status', 'ready_pickup')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($tire) {
                return [
                    'customer_name' => $tire->customer->name ?? 'Unknown Customer',
                    'vehicle' => $tire->vehicle->display_name ?? 'Unknown Vehicle',
                    'urgency' => 'Ready Now',
                ];
            });
    }

    private function getMaintenanceAlerts()
    {
        // Logic: return tires needing maintenance or inspection
        return Tire::with(['customer', 'vehicle'])
            ->where('status', 'maintenance')
            ->orWhere(function ($q) {
                $q->where('status', 'stored')
                    ->where('next_inspection_date', '<=', now()->addDays(7));
            })
            ->latest()
            ->take(5)
            ->get();
    }
}
