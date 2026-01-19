<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::withCount('vehicles');

        if ($request->filled('search')) {
            $search = $request->search;
            $normalizedSearch = strtoupper(str_replace(' ', '', trim($search)));

            $query->where(function ($q) use ($search, $normalizedSearch) {
                // Case-insensitive search on name, phone, email
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%'])
                    // Search by license plate (normalized, case-insensitive)
                    ->orWhereHas('vehicles', function ($vehicleQuery) use ($normalizedSearch) {
                        $vehicleQuery->whereRaw('UPPER(REPLACE(license_plate, \' \', \'\')) LIKE ?', ['%' . $normalizedSearch . '%']);
                    });
            });
        }

        $customers = $query->latest()->paginate(10);

        return view('customers.index', compact('customers'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $customer->load(['vehicles', 'checkins.vehicle', 'tires.vehicle', 'invoices', 'quotes']);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->get('q');

        // Search by license plate (normalized search)
        $normalizedSearch = strtoupper(str_replace(' ', '', trim($search)));

        $customers = Customer::with([
            'vehicles' => function ($query) use ($normalizedSearch) {
                $query->whereRaw('UPPER(REPLACE(license_plate, " ", "")) LIKE ?', ["%{$normalizedSearch}%"]);
            },
        ])
            ->whereHas('vehicles', function ($query) use ($normalizedSearch) {
                $query->whereRaw('UPPER(REPLACE(license_plate, " ", "")) LIKE ?', ["%{$normalizedSearch}%"]);
            })
            ->take(10)
            ->get(['id', 'name', 'phone', 'email']);

        return response()->json($customers);
    }

    public function apiShow(Customer $customer)
    {
        $customer->load('vehicles');

        return response()->json($customer);
    }

    /**
     * Get customer history (checkins, tire storage, etc.)
     */
    public function history(Customer $customer)
    {
        try {
            $customer->load(['vehicles', 'tires.vehicle']);

            $checkins = $customer->checkins()
                ->with(['vehicle'])
                ->orderBy('checkin_time', 'desc')
                ->get();

            $tires = $customer->tires()
                ->with(['vehicle'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'checkins' => $checkins->map(function ($checkin) {
                    return [
                        'id' => $checkin->id,
                        'service_type' => $checkin->service_type,
                        'service_description' => $checkin->service_description,
                        'status' => $checkin->status,
                        'priority' => $checkin->priority,
                        'checkin_time' => $checkin->checkin_time?->toIso8601String(),
                        'checkout_time' => $checkin->checkout_time?->toIso8601String(),
                        'estimated_cost' => $checkin->estimated_cost,
                        'actual_cost' => $checkin->actual_cost,
                        'vehicle' => $checkin->vehicle ? [
                            'id' => $checkin->vehicle->id,
                            'make' => $checkin->vehicle->make,
                            'model' => $checkin->vehicle->model,
                            'year' => $checkin->vehicle->year,
                            'license_plate' => $checkin->vehicle->license_plate,
                        ] : null,
                    ];
                }),
                'tires' => $tires->map(function ($tire) {
                    return [
                        'id' => $tire->id,
                        'brand' => $tire->brand,
                        'model' => $tire->model,
                        'size' => $tire->size,
                        'season' => $tire->season,
                        'quantity' => $tire->quantity,
                        'status' => $tire->status,
                        'storage_section' => $tire->storage_section,
                        'stored_at' => $tire->created_at?->toIso8601String(),
                        'vehicle' => $tire->vehicle ? [
                            'id' => $tire->vehicle->id,
                            'make' => $tire->vehicle->make,
                            'model' => $tire->vehicle->model,
                            'license_plate' => $tire->vehicle->license_plate,
                        ] : null,
                    ];
                }),
                'vehicles' => $customer->vehicles->map(function ($vehicle) {
                    return [
                        'id' => $vehicle->id,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'license_plate' => $vehicle->license_plate,
                        'color' => $vehicle->color,
                    ];
                }),
                'summary' => [
                    'total_checkins' => $checkins->count(),
                    'total_tires' => $tires->count(),
                    'total_vehicles' => $customer->vehicles->count(),
                    'active_checkins' => $checkins->whereNotIn('status', ['completed', 'cancelled'])->count(),
                    'stored_tires' => $tires->where('status', 'stored')->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer history',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
