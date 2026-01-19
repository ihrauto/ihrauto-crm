<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckinRequest;
use App\Models\Checkin;
use App\Models\Customer;
use App\Models\User;
use App\Traits\ChecksTechnicianAvailability;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    use ChecksTechnicianAvailability;

    public function index()
    {
        // Clear any persistent session flash data on fresh page loads
        // This prevents the success notification from appearing when it shouldn't
        if (!request()->hasHeader('referer') || !str_contains(request()->header('referer'), 'checkin')) {
            session()->forget(['success', 'error']);
        }

        // Get check-in statistics
        $checkin_stats = [
            'today_checkins' => Checkin::today()->count(),
            'in_progress' => Checkin::inProgress()->count(),
            'completed' => Checkin::completed()->whereDate('checkout_time', today())->count(),
            'avg_time' => $this->calculateAverageServiceTime(),
        ];

        // Get active check-ins
        $active_checkins = Checkin::with(['customer', 'vehicle'])
            ->active()
            ->latest('checkin_time')
            ->get();

        // Get archived check-ins (completed with checkout time) - last 12 months
        $archived_checkins = Checkin::with(['customer', 'vehicle'])
            ->completed()
            ->whereNotNull('checkout_time')
            ->where('checkout_time', '>=', now()->subMonths(12))
            ->latest('checkout_time')
            ->get();

        // Get service bay status
        $service_bays = $this->getServiceBayStatus();

        // Get active services grouped by category
        $services = \App\Models\Service::where('is_active', true)
            ->get()
            ->groupBy('category');

        // Get technicians for assignment dropdown
        $users = User::where('is_active', true)->orderBy('name')->get();

        // Get busy technician IDs (those with in_progress work orders)
        $busy_technician_ids = $this->getBusyTechnicianIds();

        return view('checkin', compact('checkin_stats', 'active_checkins', 'archived_checkins', 'service_bays', 'services', 'users', 'busy_technician_ids'));
    }

    protected $checkinService;

    public function __construct(\App\Services\CheckinService $checkinService)
    {
        $this->checkinService = $checkinService;
    }

    public function store(StoreCheckinRequest $request)
    {
        // Debug logging
        \Log::info('=== CHECK-IN FORM SUBMISSION ===');
        \Log::info('Form type: ' . $request->form_type);

        try {
            if ($request->form_type === 'active_user') {
                $checkin = $this->checkinService->createForExistingVehicle($request->validated());
            } else {
                $checkin = $this->checkinService->createWithNewRegistration($request->validated());
            }

            // Calculate initial tasks from checkin service_type
            $tasks = [];
            $partsUsed = [];
            if (!empty($checkin->service_type)) {
                $serviceNames = explode(',', $checkin->service_type); // assuming comma separated
                foreach ($serviceNames as $name) {
                    $name = trim($name);
                    if (empty($name)) {
                        continue;
                    }

                    // Try to find service for price
                    // We check both 'name' and 'slug' or similar if needed.
                    // Assuming 'name' matches the stored string (e.g. 'oil_change')
                    $service = \App\Models\Service::with('products')->where('name', $name)
                        ->orWhere('name', str_replace('_', ' ', $name)) // try flexible match
                        ->first();

                    $tasks[] = [
                        'name' => $service ? $service->name : ucfirst(str_replace('_', ' ', $name)),
                        'completed' => false,
                        'price' => $service ? $service->price : 0,
                    ];

                    // Collect parts from Service BOM
                    if ($service && $service->products->isNotEmpty()) {
                        foreach ($service->products as $product) {
                            $partsUsed[] = [
                                'product_id' => $product->id,
                                'name' => $product->name,
                                'qty' => $product->pivot->quantity,
                                'price' => $product->price,
                            ];
                        }
                    }
                }
            }

            // Check technician availability before assigning
            $technicianId = $request->technician_id ?: null;
            if ($technicianId && $this->isTechnicianBusy($technicianId)) {
                return back()->withInput()->with('error', 'Selected technician is currently busy with another job.');
            }

            // Automate Work Order Creation
            $workOrder = \App\Models\WorkOrder::create([
                'tenant_id' => auth()->user()->tenant_id,
                'checkin_id' => $checkin->id,
                'customer_id' => $checkin->customer_id,
                'vehicle_id' => $checkin->vehicle_id,
                'technician_id' => $technicianId,
                'status' => 'created', // Same as tire hotel - enables "Start Job" button
                'priority' => 'normal',
                'description' => "Auto-created from Check-in #{$checkin->id} - Service: " . ($checkin->service_type ?? 'General'),
                'service_tasks' => $tasks, // Successfully populate tasks!
                'parts_used' => $partsUsed, // Auto-populate from Service BOM
                'created_by' => auth()->id(),
            ]);

            // Track checkin created event
            app(\App\Services\EventTracker::class)->trackSimple('checkin_created');

            // Handle Photo Uploads (Before Service)
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $filename = \Illuminate\Support\Str::uuid() . '.' . $photo->getClientOriginalExtension();
                    $path = "work-order-photos/{$workOrder->tenant_id}/{$workOrder->id}/{$filename}";

                    \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($photo));

                    \App\Models\WorkOrderPhoto::create([
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
            }

            // Redirect to Work Order Show Page (Job Sheet) - same as tire hotel
            return redirect()->route('work-orders.show', $workOrder)
                ->with('success', 'Check-in completed! Work Order #' . $workOrder->id . ' created. You can now start the job.');

        } catch (\Exception $e) {
            \Log::error('Check-in error: ' . $e->getMessage());

            return back()
                ->withInput()
                ->with('error', 'Error processing check-in: ' . $e->getMessage());
        }
    }

    public function show(Checkin $checkin)
    {
        $checkin->load(['customer', 'vehicle']);

        return view('checkin.show', compact('checkin'));
    }

    public function update(Request $request, Checkin $checkin)
    {
        // Defense in depth: verify ownership even though route model binding scopes by tenant
        if ($checkin->tenant_id !== auth()->user()->tenant_id) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,cancelled,done',
            'assigned_technician' => 'nullable|string|max:100',
            'technician_notes' => 'nullable|string|max:1000',
            'actual_cost' => 'nullable|numeric|min:0',
            'checkout_time' => 'nullable|date',
        ]);

        $updates = $request->only(['status', 'assigned_technician', 'technician_notes', 'actual_cost']);

        if ($request->status === 'completed' && !$checkin->checkout_time) {
            $updates['checkout_time'] = now();
        }

        if ($request->status === 'done') {
            $updates['status'] = 'completed';
            $updates['checkout_time'] = now();
        }

        $checkin->update($updates);

        if ($request->status === 'done') {
            $checkin->load('vehicle');
            $vehicleName = $checkin->vehicle ? $checkin->vehicle->display_name : 'Unknown Vehicle';
            return back()->with('success', 'Check-in completed and archived successfully! Service for ' . $vehicleName . ' has been finalized.');
        }

        return back()->with('success', 'Check-in updated successfully.');
    }

    private function calculateAverageServiceTime()
    {
        $completed_checkins = Checkin::completed()
            ->whereNotNull('checkout_time')
            ->whereDate('checkout_time', today())
            ->get();

        if ($completed_checkins->isEmpty()) {
            return '2.5h'; // Default if no data
        }

        $total_minutes = $completed_checkins->sum(function ($checkin) {
            return $checkin->checkin_time->diffInMinutes($checkin->checkout_time);
        });

        $avg_minutes = $total_minutes / $completed_checkins->count();
        $hours = floor($avg_minutes / 60);
        $minutes = $avg_minutes % 60;

        return $hours > 0 ? $hours . 'h ' . round($minutes) . 'm' : round($minutes) . 'm';
    }

    private function getServiceBayStatus()
    {
        // Fetch bays from database (auto-seeds defaults if empty)
        $serviceBays = \App\Models\ServiceBay::active()->ordered()->get();

        // If no bays exist, seed defaults for this tenant
        if ($serviceBays->isEmpty()) {
            $this->seedDefaultBays();
            $serviceBays = \App\Models\ServiceBay::active()->ordered()->get();
        }

        $bay_status = [];

        foreach ($serviceBays as $serviceBay) {
            $active_checkins = Checkin::where('service_bay', $serviceBay->name)
                ->whereIn('status', ['pending', 'in_progress'])
                ->get();

            $count = $active_checkins->count();
            $first_checkin = $active_checkins->first();

            $bay_status[] = [
                'name' => $serviceBay->name,
                'status' => $count > 0 ? 'in_use' : 'available',
                'count' => $count,
                'checkin' => $first_checkin,
                'checkins' => $active_checkins,
            ];
        }

        return $bay_status;
    }

    /**
     * Seed default bays for the current tenant.
     */
    private function seedDefaultBays()
    {
        $tenantId = auth()->user()->tenant_id;
        $defaultBays = ['Bay 1', 'Bay 2', 'Bay 3', 'Bay 4', 'Bay 5', 'Bay 6'];

        foreach ($defaultBays as $index => $name) {
            \App\Models\ServiceBay::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * Get customer history for API
     */
    public function getCustomerHistory(Customer $customer)
    {
        try {
            $checkins = $customer->checkins()
                ->with(['vehicle'])
                ->orderBy('checkin_time', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'checkins' => $checkins,
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer history',
            ], 500);
        }
    }

    /**
     * Get customer details for API
     */
    public function getCustomerDetails(Customer $customer)
    {
        try {
            $customer->load('vehicles');

            return response()->json([
                'success' => true,
                'customer' => $customer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer details',
            ], 500);
        }
    }
}
