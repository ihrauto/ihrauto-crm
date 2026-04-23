<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Support\TenantValidation;
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

        // C-10: colour palette lives in config/crm.php so non-engineers can
        // tune the calendar look without touching PHP.
        $colors = config('crm.appointment_status_colors', [
            'scheduled' => '#6366f1',
        ]);
        $defaultColor = $colors['scheduled'] ?? '#6366f1';

        $events = $appointments->map(function ($apt) use ($colors, $defaultColor) {
            return [
                'id' => $apt->id,
                'title' => $apt->customer ? $apt->customer->name : ($apt->title ?? 'Appointment'),
                'start' => $apt->start_time->toIso8601String(),
                'end' => $apt->end_time ? $apt->end_time->toIso8601String() : $apt->start_time->copy()->addHour()->toIso8601String(),
                'backgroundColor' => $colors[$apt->status] ?? $defaultColor,
                'borderColor' => $colors[$apt->status] ?? $defaultColor,
                'extendedProps' => [
                    'customer_id' => $apt->customer_id,
                    'customer_name' => $apt->customer?->name,
                    'customer_phone' => $apt->customer?->phone,
                    'vehicle' => $apt->vehicle ? ($apt->vehicle->make.' '.$apt->vehicle->model.' ('.$apt->vehicle->license_plate.')') : null,
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
        $this->authorize('update', $appointment);

        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'nullable|date',
        ]);

        // B-12: compute the original duration BEFORE mutating start_time,
        // otherwise the diff runs between the new start and old end and
        // produces a negative or wildly wrong duration. Default to 60 min
        // only when the original appointment had no end time at all.
        $originalDuration = ($appointment->start_time && $appointment->end_time)
            ? (int) $appointment->start_time->diffInMinutes($appointment->end_time)
            : 60;

        $appointment->start_time = Carbon::parse($validated['start']);

        if (! empty($validated['end'])) {
            $appointment->end_time = Carbon::parse($validated['end']);
        } else {
            $appointment->end_time = $appointment->start_time->copy()->addMinutes($originalDuration);
        }

        $appointment->save();

        return response()->json(['success' => true, 'message' => 'Appointment rescheduled.']);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Appointment::class);

        $request->validate([
            'customer_id' => ['nullable', TenantValidation::exists('customers')],
        ]);

        // Check if this is a new customer
        $customerId = $request->customer_id;

        if (empty($customerId) && $request->filled('new_customer_name')) {
            // Validate new customer fields
            $request->validate([
                'new_customer_name' => 'required|string|max:255',
                'new_customer_phone' => 'required|string|max:20',
            ]);

            // B-01: enforce plan customer limit before creating.
            \App\Support\PlanQuota::assertCanAddCustomer();

            // Create new customer
            $newCustomer = Customer::create([
                'tenant_id' => tenant_id(),
                'name' => $request->new_customer_name,
                'phone' => $request->new_customer_phone,
            ]);

            $customerId = $newCustomer->id;
        }

        $validated = $request->validate([
            'vehicle_id' => ['nullable', TenantValidation::exists('vehicles')],
            'start_date' => 'required|date',
            'start_time' => 'required',
            'duration' => 'required|integer|min:15', // minutes
            'type' => 'required|string',
            'title' => 'nullable|string|max:255',
            'status' => 'required|in:scheduled,confirmed',
            'notes' => 'nullable|string',
        ]);

        if (! $customerId) {
            return redirect()->back()->withErrors(['customer_id' => 'Please select or create a customer.']);
        }

        $startDateTime = Carbon::parse($request->start_date.' '.$request->start_time);
        $endDateTime = $startDateTime->copy()->addMinutes((int) $request->duration);

        // Auto-assign first vehicle if not selected
        $vehicleId = $validated['vehicle_id'] ?? null;
        if (! $vehicleId && $customer = Customer::query()->find($customerId)) {
            $vehicleId = $customer->vehicles()->first()->id ?? null;
        }

        // Check for conflicting appointments on the same vehicle
        if ($vehicleId) {
            $conflict = Appointment::where('vehicle_id', $vehicleId)
                ->whereNotIn('status', ['cancelled', 'completed', 'failed'])
                ->where('start_time', '<', $endDateTime)
                ->where('end_time', '>', $startDateTime)
                ->first();

            if ($conflict) {
                return redirect()->back()->withInput()->withErrors([
                    'start_time' => 'This vehicle already has an appointment at '.$conflict->start_time->format('M j, g:i A').'. Please choose a different time.',
                ]);
            }
        }

        Appointment::create([
            'tenant_id' => tenant_id(),
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'title' => $validated['title'] ?? ucfirst(str_replace('_', ' ', $validated['type'])),
            'start_time' => $startDateTime,
            'end_time' => $endDateTime,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Appointment scheduled successfully.');
    }

    /**
     * Update the specified appointment.
     */
    public function update(Request $request, Appointment $appointment)
    {
        $this->authorize('update', $appointment);

        // Full Update
        if ($request->has('start_date') && $request->has('start_time')) {
            $validated = $request->validate([
                'customer_id' => ['required', TenantValidation::exists('customers')],
                'start_date' => 'required|date',
                'start_time' => 'required',
                'duration' => 'required|integer|min:15',
                'type' => 'required|string',
                'notes' => 'nullable|string',
            ]);

            $startDateTime = Carbon::parse($request->start_date.' '.$request->start_time);
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
                $appointment->notes = ($appointment->notes ? $appointment->notes."\n\n" : '').'FAILED REASON: '.$request->notes;
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
        $this->authorize('delete', $appointment);

        $appointment->delete();

        return redirect()->back()->with('success', 'Appointment cancelled.');
    }
}
