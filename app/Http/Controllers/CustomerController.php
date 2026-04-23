<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Services\CustomerMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('vehicles')->withCount('vehicles');

        if ($request->filled('search')) {
            $search = $request->search;
            [$plateExpr, $plateBindings] = \App\Support\LicensePlate::whereExpression($search, like: true);

            $query->where(function ($q) use ($search, $plateExpr, $plateBindings) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(phone) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.strtolower($search).'%'])
                    ->orWhereHas('vehicles', function ($vehicleQuery) use ($plateExpr, $plateBindings) {
                        $vehicleQuery->whereRaw($plateExpr, $plateBindings);
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
        $this->authorize('create', Customer::class);

        // B-01: enforce plan customer limit.
        \App\Support\PlanQuota::assertCanAddCustomer();

        $customer = Customer::create($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        $customer->load(['vehicles', 'checkins.vehicle', 'tires.vehicle', 'invoices', 'quotes']);

        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $this->authorize('update', $customer);

        return view('customers.edit', compact('customer'));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        /*
         * Bug review DATA-04: the dependency count + delete was a
         * read-modify-write without a lock or enclosing transaction. Races:
         *   - Two admins clicking "Delete" simultaneously both pass the
         *     dependency check, both issue delete. One succeeds, the other
         *     hits "model not found" — ugly but not data-corrupting.
         *   - Worse: a new invoice / work order is created between our
         *     count and our delete. The customer gets soft-deleted with
         *     dependent rows referencing a dead customer, leaving orphan
         *     data that surfaces later as a reporting bug.
         *
         * The fix wraps the whole probe + delete in a transaction with
         * lockForUpdate on the customer row. Concurrent deletions now
         * serialise; any caller trying to create a dependent row while we
         * hold the lock either waits for our transaction to commit or
         * rolls back when our delete wins.
         */
        $blockingDependencies = null;

        DB::transaction(function () use ($customer, &$blockingDependencies) {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            $dependencies = [
                'vehicles' => $locked->vehicles()->count(),
                'check-ins' => $locked->checkins()->count(),
                'tire records' => $locked->tires()->count(),
                'work orders' => \App\Models\WorkOrder::where('customer_id', $locked->id)->count(),
                'invoices' => $locked->invoices()->count(),
                'quotes' => $locked->quotes()->count(),
                'payments' => $locked->payments()->count(),
            ];

            $blockingDependencies = collect($dependencies)
                ->filter(fn (int $count) => $count > 0)
                ->map(fn (int $count, string $label) => "{$count} {$label}")
                ->values();

            if ($blockingDependencies->isEmpty()) {
                $locked->delete();
            }
        });

        if ($blockingDependencies && $blockingDependencies->isNotEmpty()) {
            return redirect()->route('customers.show', $customer)
                ->with('error', 'Cannot delete this customer while linked records exist: '.$blockingDependencies->join(', ').'. Remove or archive the linked data first.');
        }

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->get('query') ?? $request->get('q');

        if (! $search || strlen($search) < 2) {
            return response()->json([]);
        }

        $searchTerm = strtolower(trim($search));
        [$plateExpr, $plateBindings] = \App\Support\LicensePlate::whereExpression($search, like: true);

        $customers = Customer::with([
            'vehicles' => function ($query) use ($plateExpr, $plateBindings) {
                $query->whereRaw($plateExpr, $plateBindings);
            },
        ])
            ->where(function ($q) use ($searchTerm, $plateExpr, $plateBindings) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"])
                    ->orWhereHas('vehicles', function ($vq) use ($plateExpr, $plateBindings) {
                        $vq->whereRaw($plateExpr, $plateBindings);
                    });
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
     * Merge two customer records. The `primary` customer survives; the
     * `duplicate` is soft-deleted after all its related records (vehicles,
     * checkins, work orders, invoices, quotes, tires, appointments) are
     * transferred to the primary.
     *
     * Requires both delete and update permission on customers plus the
     * `delete-records` gate (same as destroy).
     */
    public function merge(Request $request, CustomerMergeService $merger)
    {
        $validated = $request->validate([
            'primary_id' => ['required', 'integer', 'different:duplicate_id'],
            'duplicate_id' => ['required', 'integer'],
        ]);

        $primary = Customer::findOrFail($validated['primary_id']);
        $duplicate = Customer::findOrFail($validated['duplicate_id']);

        $this->authorize('update', $primary);
        $this->authorize('delete', $duplicate);
        \Illuminate\Support\Facades\Gate::authorize('delete-records');

        try {
            $merged = $merger->merge($primary, $duplicate);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('customers.show', $merged)
            ->with('success', 'Customers merged successfully.');
    }

    public function vehiclesByCustomer(Customer $customer)
    {
        return response()->json($customer->vehicles()->get());
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
