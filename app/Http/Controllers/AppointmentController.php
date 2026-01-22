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

        // Get customers for the "New Appointment" modal
        $customers = Customer::orderBy('name')->limit(100)->get();

        return view('appointments.index', compact('customers'));
    }

    /**
     * Return appointments as JSON for FullCalendar.
     */
    public function events(Request $request)
    {
        $start = $request->input('start');
        $end = $request->input('end');

        $query = Appointment::with(['customer', 'vehicle']);

        if ($start && $end) {
            $query->whereBetween('start_time', [$start, $end]);
        }

        $appointments = $query->orderBy('start_time')->get();

        $events = $appointments->map(function ($apt) {
            // Color based on status
            $colors = [
                'scheduled' => '#6366f1',  // Indigo
                'confirmed' => '#8b5cf6',  // Purple
                'completed' => '#22c55e',  // Green
                'failed' => '#ef4444',     // Red
                'cancelled' => '#9ca3af',  // Gray
            ];

            return [
                'id' => $apt->id,
                'title' => $apt->customer ? $apt->customer->name : ($apt->title ?? 'Appointment'),
                'start' => $apt->start_time->toIso8601String(),
                'end' => $apt->end_time ? $apt->end_time->toIso8601String() : $apt->start_time->copy()->addHour()->toIso8601String(),
                'backgroundColor' => $colors[$apt->status] ?? $colors['scheduled'],
                'borderColor' => $colors[$apt->status] ?? $colors['scheduled'],
                'extendedProps' => [
                    'customer_id' => $apt->customer_id,
                    'customer_name' => $apt->customer?->name,
                    'customer_phone' => $apt->customer?->phone,
                    'vehicle' => $apt->vehicle ? ($apt->vehicle->make . ' ' . $apt->vehicle->model . ' (' . $apt->vehicle->license_plate . ')') : null,
                    'type' => $apt->type,
                    'type_label' => ucfirst(str_replace('_', ' ', $apt->type)),
                    'status' => $apt->status,
                    'notes' => $apt->notes,
                    'duration' => $apt->end_time ? $apt->start_time->diffInMinutes($apt->end_time) : 60,
                ],
            ];
        });

        return response()->json($events);
    }

    /**
     * Quick reschedule via drag-and-drop.
     */
    public function reschedule(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'nullable|date',
        ]);

        $appointment->start_time = Carbon::parse($validated['start']);

        if (!empty($validated['end'])) {
            $appointment->end_time = Carbon::parse($validated['end']);
        } else {
            // Maintain original duration
            $duration = $appointment->end_time
                ? $appointment->start_time->diffInMinutes($appointment->end_time)
                : 60;
            $appointment->end_time = $appointment->start_time->copy()->addMinutes($duration);
        }

        $appointment->save();

        return response()->json(['success' => true, 'message' => 'Appointment rescheduled.']);
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
