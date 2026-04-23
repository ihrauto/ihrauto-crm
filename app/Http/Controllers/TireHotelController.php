<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewTireCustomerRequest;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TireStorageService;
use App\Support\TenantValidation;
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

        $upcoming_pickups = $this->tireStorageService->getUpcomingPickups();
        $maintenance_alerts = $this->tireStorageService->getMaintenanceAlerts();

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
        $this->authorize('create', Tire::class);

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

            $workOrder = $this->tireStorageService->createTireWorkOrder($result['tire'], 'New Customer Storage', $technicianId);

            // Track tire hotel created event
            app(\App\Services\EventTracker::class)->trackSimple('tirehotel_created');

            DB::commit();

            return redirect()->route('work-orders.show', $workOrder)->with(
                'success',
                'Tires stored successfully. Work order created.'
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error storing tires', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);

            // Never surface raw exception messages to end users.
            return back()
                ->withInput()
                ->with('error', 'Could not save the tire storage record. Please try again or contact support if it persists.');
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
            'customer_id' => ['required', TenantValidation::exists('customers')],
            'vehicle_id' => ['required', TenantValidation::exists('vehicles')],
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
        $searchTerm = strtolower(trim($query));

        // Search directly on Tire model, looking at customer name, vehicle info, and storage location
        $tires = Tire::with(['customer', 'vehicle'])
            ->where(function ($q) use ($searchTerm) {
                // Storage Location (case-insensitive)
                $q->whereRaw('LOWER(storage_location) LIKE ?', ["%{$searchTerm}%"])
                    // Customer Name (case-insensitive, partial match)
                    ->orWhereHas('customer', function ($cq) use ($searchTerm) {
                        $cq->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
                    })
                    // Vehicle License Plate (case-insensitive)
                    ->orWhereHas('vehicle', function ($vq) use ($searchTerm) {
                        $vq->whereRaw('LOWER(license_plate) LIKE ?', ["%{$searchTerm}%"])
                            ->orWhereRaw('LOWER(make) LIKE ?', ["%{$searchTerm}%"])
                            ->orWhereRaw('LOWER(model) LIKE ?', ["%{$searchTerm}%"]);
                    });
            })
            ->get();

        if ($tires->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No tires found']);
        }

        // Get the first tire's vehicle for display purposes
        $firstTire = $tires->first();
        $vehicle = $firstTire->vehicle;

        // Load customer for the response
        if ($vehicle) {
            $vehicle->load('customer');
        }

        return response()->json([
            'success' => true,
            'vehicle' => $vehicle ?? (object) ['make' => 'Unknown', 'model' => 'Vehicle', 'customer' => $firstTire->customer],
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
        $this->authorize('update', $tire);

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
        $this->authorize('update', $tire);

        try {
            $workOrder = $this->tireStorageService->createTireWorkOrder($tire, 'Manual Generation');

            return redirect()->route('work-orders.show', $workOrder)
                ->with('success', "Work Order #{$workOrder->id} created for tire job.");

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate work order: '.$e->getMessage());
        }
    }
}
