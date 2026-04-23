<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckinRequest;
use App\Models\Checkin;
use App\Models\Customer;
use App\Models\User;
use App\Traits\ChecksTechnicianAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{
    use ChecksTechnicianAvailability;

    public function index()
    {
        // Clear any persistent session flash data on fresh page loads
        // This prevents the success notification from appearing when it shouldn't
        if (! request()->hasHeader('referer') || ! str_contains(request()->header('referer'), 'checkin')) {
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
        $uploadedPaths = [];

        try {
            $technicianId = $request->technician_id ?: null;
            if ($technicianId && $this->isTechnicianBusy($technicianId)) {
                return back()->withInput()->with('error', 'Selected technician is currently busy with another job.');
            }

            DB::beginTransaction();

            $checkin = $request->form_type === 'active_user'
                ? $this->checkinService->createForExistingVehicle($request->validated())
                : $this->checkinService->createWithNewRegistration($request->validated());

            $workOrder = $this->checkinService->createWorkOrderFromCheckin($checkin, $technicianId);

            if ($request->hasFile('photos')) {
                $uploadedPaths = $this->checkinService->uploadPhotos($workOrder, $request->file('photos'));
            }

            app(\App\Services\EventTracker::class)->trackSimple('checkin_created');
            DB::commit();

            return redirect()->route('work-orders.show', $workOrder)
                ->with('success', 'Check-in completed! Work Order #'.$workOrder->id.' created. You can now start the job.');

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->checkinService->cleanupPhotos($uploadedPaths);
            \Log::error('Check-in error', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            report($e);

            // Never surface raw exception messages to end users — they can
            // leak internal paths, SQL fragments, or resource IDs.
            return back()
                ->withInput()
                ->with('error', 'Could not complete check-in. Please try again or contact support if it persists.');
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
        if ($checkin->tenant_id !== tenant_id()) {
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

        if ($request->status === 'completed' && ! $checkin->checkout_time) {
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

            return back()->with('success', 'Check-in completed and archived successfully! Service for '.$vehicleName.' has been finalized.');
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
            return 'No data';
        }

        $total_minutes = $completed_checkins->sum(function ($checkin) {
            return $checkin->checkin_time->diffInMinutes($checkin->checkout_time);
        });

        $avg_minutes = $total_minutes / $completed_checkins->count();
        $hours = floor($avg_minutes / 60);
        $minutes = $avg_minutes % 60;

        return $hours > 0 ? $hours.'h '.round($minutes).'m' : round($minutes).'m';
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

        // Single query for all active check-ins, then group by bay in PHP (fixes N+1)
        $allActiveCheckins = Checkin::whereIn('status', ['pending', 'in_progress'])->get();
        $checkinsByBay = $allActiveCheckins->groupBy('service_bay');

        $bay_status = [];

        foreach ($serviceBays as $serviceBay) {
            $bayCheckins = $checkinsByBay->get($serviceBay->name, collect());

            $bay_status[] = [
                'name' => $serviceBay->name,
                'status' => $bayCheckins->isNotEmpty() ? 'in_use' : 'available',
                'count' => $bayCheckins->count(),
                'checkin' => $bayCheckins->first(),
                'checkins' => $bayCheckins,
            ];
        }

        return $bay_status;
    }

    /**
     * Seed default bays for the current tenant.
     */
    private function seedDefaultBays()
    {
        $tenantId = tenant_id();
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
