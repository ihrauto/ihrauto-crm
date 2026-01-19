<?php

namespace App\Http\Controllers;

use App\Models\ServiceBay;
use Illuminate\Http\Request;

class ServiceBayController extends Controller
{
    /**
     * Display the service bays management page.
     */
    public function index()
    {
        $bays = ServiceBay::ordered()->get();

        // If no bays exist for this tenant, seed defaults
        if ($bays->isEmpty()) {
            $this->seedDefaultBays();
            $bays = ServiceBay::ordered()->get();
        }

        return view('work-bays.index', compact('bays'));
    }

    /**
     * Store a new service bay.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $maxOrder = ServiceBay::max('sort_order') ?? 0;

        ServiceBay::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $request->name,
            'is_active' => true,
            'sort_order' => $maxOrder + 1,
        ]);

        return back()->with('success', 'Bay added successfully.');
    }

    /**
     * Update an existing service bay.
     */
    public function update(Request $request, ServiceBay $serviceBay)
    {
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $serviceBay->update([
            'name' => $request->name,
        ]);

        return back()->with('success', 'Bay updated successfully.');
    }

    /**
     * Delete a service bay.
     */
    public function destroy(ServiceBay $serviceBay)
    {
        // Check if bay is in use by any active checkins
        $inUse = \App\Models\Checkin::where('service_bay', $serviceBay->name)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete bay: it is currently in use by active check-ins.');
        }

        $serviceBay->delete();

        return back()->with('success', 'Bay deleted successfully.');
    }

    /**
     * Bulk update all bays (for saving all changes at once).
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'bays' => 'required|array',
            'bays.*.id' => 'required|integer|exists:service_bays,id',
            'bays.*.name' => 'required|string|max:100',
        ]);

        foreach ($request->bays as $index => $bayData) {
            ServiceBay::where('id', $bayData['id'])->update([
                'name' => $bayData['name'],
                'sort_order' => $index,
            ]);
        }

        return back()->with('success', 'All changes saved successfully.');
    }

    /**
     * Seed default bays for new tenants.
     */
    private function seedDefaultBays()
    {
        $tenantId = auth()->user()->tenant_id;
        $defaultBays = ['Bay 1', 'Bay 2', 'Bay 3', 'Bay 4', 'Bay 5', 'Bay 6'];

        foreach ($defaultBays as $index => $name) {
            ServiceBay::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
