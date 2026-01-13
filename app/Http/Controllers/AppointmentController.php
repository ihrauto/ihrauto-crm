<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Display a listing of appointments (Calendar/List).
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Default to current week or provided date
        $date = $request->input('date', now()->format('Y-m-d'));
        $startOfWeek = Carbon::parse($date)->startOfWeek();
        $endOfWeek = Carbon::parse($date)->endOfWeek();

        // Fetch appointments for this week
        $query = Appointment::with(['customer', 'vehicle'])
            ->whereBetween('start_time', [$startOfWeek, $endOfWeek])
            ->orderBy('start_time');

        // Filter by ownership if user cannot view all appointments
        // Note: For now, we show all appointments to technicians since they may need to see the schedule.
        // Uncomment the filter below if you add a user_id/assigned_to_id field to appointments.
        // if (!$user->can('view all appointments')) {
        //     $query->where('user_id', $user->id);
        // }

        $appointments = $query->get();

        // Group by Date for easier display in grid
        $appointmentsByDate = $appointments->groupBy(function ($date) {
            return Carbon::parse($date->start_time)->format('Y-m-d');
        });

        // Get customers for the "New Appointment" modal
        $customers = Customer::orderBy('name')->limit(50)->get();

        return view('appointments.index', compact('appointmentsByDate', 'startOfWeek', 'endOfWeek', 'customers'));
    }

    /**
     * Store a newly created appointment.
     */
    public function store(Request $request)
    {
        // Check if this is a new customer
        $customerId = $request->customer_id;

        if (empty($customerId) && $request->filled('new_customer_name')) {
            // Validate new customer fields
            $request->validate([
                'new_customer_name' => 'required|string|max:255',
                'new_customer_phone' => 'required|string|max:20',
            ]);

            // Create new customer
            $newCustomer = Customer::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $request->new_customer_name,
                'phone' => $request->new_customer_phone,
            ]);

            $customerId = $newCustomer->id;
        }

        $validated = $request->validate([
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'start_date' => 'required|date',
            'start_time' => 'required',
            'duration' => 'required|integer|min:15', // minutes
            'type' => 'required|string',
            'title' => 'nullable|string|max:255',
            'status' => 'required|in:scheduled,confirmed',
            'notes' => 'nullable|string',
        ]);

        if (!$customerId) {
            return redirect()->back()->withErrors(['customer_id' => 'Please select or create a customer.']);
        }

        $startDateTime = Carbon::parse($request->start_date . ' ' . $request->start_time);
        $endDateTime = $startDateTime->copy()->addMinutes((int) $request->duration);

        // Auto-assign first vehicle if not selected (Optional, but helpful)
        $vehicleId = $validated['vehicle_id'] ?? null;
        if (!$vehicleId && $customer = Customer::find($customerId)) {
            $vehicleId = $customer->vehicles()->first()->id ?? null;
        }

        Appointment::create([
            'tenant_id' => auth()->user()->tenant_id,
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'title' => $validated['title'] ?? ucfirst(str_replace('_', ' ', $validated['type'])),
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->back()->with('success', 'Appointment scheduled successfully.');
    }

    /**
     * Update the specified appointment.
     */
    public function update(Request $request, Appointment $appointment)
    {
        // Full Update
        if ($request->has('start_date') && $request->has('start_time')) {
            $validated = $request->validate([
                'customer_id' => 'required|exists:customers,id',
                'start_date' => 'required|date',
                'start_time' => 'required',
                'duration' => 'required|integer|min:15',
                'type' => 'required|string',
                'notes' => 'nullable|string',
            ]);

            $startDateTime = Carbon::parse($request->start_date . ' ' . $request->start_time);
            $endDateTime = $startDateTime->copy()->addMinutes((int) $request->duration);

            $appointment->update([
                'customer_id' => $validated['customer_id'],
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'type' => $validated['type'],
                'notes' => $validated['notes'],
                'title' => ucfirst(str_replace('_', ' ', $validated['type'])),
            ]);

            return redirect()->back()->with('success', 'Appointment details updated.');
        }

        // Simple status update (Drag & Drop or Quick Action)
        if ($request->has('status')) {
            $validated = $request->validate([
                'status' => 'required',
                'notes' => 'nullable|string',
            ]);

            if ($request->status === 'failed' && $request->has('notes')) {
                // Append failure reason to notes
                $appointment->notes = ($appointment->notes ? $appointment->notes . "\n\n" : '') . 'FAILED REASON: ' . $request->notes;
            }

            $appointment->status = $request->status;
            $appointment->save();

            return redirect()->back()->with('success', 'Appointment status updated.');
        }

        return redirect()->back();
    }

    /**
     * Remove the specified appointment.
     */
    public function destroy(Appointment $appointment)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        $appointment->delete();

        return redirect()->back()->with('success', 'Appointment cancelled.');
    }
}
