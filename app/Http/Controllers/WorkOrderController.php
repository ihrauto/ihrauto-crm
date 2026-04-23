<?php

namespace App\Http\Controllers;

use App\Enums\CheckinStatus;
use App\Enums\WorkOrderStatus;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\Checkin;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use App\Services\WorkOrderService;
use App\Support\TenantValidation;
use App\Traits\ChecksTechnicianAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    use ChecksTechnicianAvailability;

    public function __construct(
        protected InvoiceService $invoiceService,
        protected WorkOrderService $workOrderService,
    ) {}

    /**
     * Generate an invoice from a Work Order (Manual Action).
     */
    public function generateInvoice(WorkOrder $workOrder)
    {
        $this->authorize('generateInvoice', $workOrder);

        if ($workOrder->invoice) {
            return redirect()->route('finance.index', ['tab' => 'invoices'])
                ->with('info', "Invoice {$workOrder->invoice->invoice_number} already exists for this Work Order.");
        }

        try {
            DB::transaction(function () use ($workOrder) {
                $this->invoiceService->createFromWorkOrder($workOrder);
            });

            return redirect()->route('finance.index', ['tab' => 'invoices'])
                ->with('success', 'Invoice generated from Work Order.');

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to generate invoice: '.$e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = WorkOrder::whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
            ->with(['vehicle', 'customer', 'technician'])
            ->latest();

        // Filter by ownership if user cannot view all work orders
        if (! $user->can('view all work-orders')) {
            $query->where('technician_id', $user->id);
        }

        if ($request->has('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $active_orders = $query->get();

        $completedQuery = WorkOrder::whereIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value])
            ->with(['vehicle', 'customer', 'technician'])
            ->latest('completed_at');

        // Filter by completed date (default: today)
        $completedDate = $request->get('completed_date', now()->toDateString());
        $completedQuery->whereDate('completed_at', $completedDate);

        // Also filter completed orders by ownership
        if (! $user->can('view all work-orders')) {
            $completedQuery->where('technician_id', $user->id);
        }

        $completed_orders = $completedQuery->get();

        return view('work-orders.index', compact('active_orders', 'completed_orders', 'completedDate'));
    }

    public function board()
    {
        $technicians = User::where('is_active', true)
            ->with([
                'workOrders' => function ($query) {
                    $query->where('status', WorkOrderStatus::InProgress->value)
                        ->with(['customer', 'vehicle'])
                        ->latest();
                },
            ])
            ->orderBy('name')
            ->get();

        return view('work-orders.board', compact('technicians'));
    }

    public function employeeStats()
    {
        // Technicians go directly to their own stats page
        if (! auth()->user()->can('access management')) {
            return redirect()->route('work-orders.employee-details', auth()->user());
        }

        $users = User::where('is_active', true)->orderBy('name')->get();

        return view('work-orders.employee_stats', compact('users'));
    }

    public function showEmployeeStats(Request $request, User $user)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = $user->workOrders()->where('status', WorkOrderStatus::Completed->value);

        if ($year) {
            $query->whereYear('completed_at', $year);
        }

        if ($month) {
            $query->whereMonth('completed_at', $month);
        }

        $workOrders = $query->latest('completed_at')->get();

        // Calculate Stats
        $totalJobs = $workOrders->count();
        $totalMinutes = $workOrders->sum(function ($wo) {
            if ($wo->started_at && $wo->completed_at) {
                return $wo->started_at->diffInMinutes($wo->completed_at);
            }

            return 0;
        });
        $totalHours = round($totalMinutes / 60, 1);
        $avgTime = $totalJobs > 0 ? round($totalHours / $totalJobs, 1) : 0;

        return view('work-orders.employee_details', compact('user', 'workOrders', 'totalJobs', 'totalHours', 'avgTime', 'year', 'month'));
    }

    public function create(Request $request)
    {
        // If passing a checkin_id (legacy or direct link from checkin), handle it
        if ($request->has('checkin_id')) {
            $checkin = Checkin::query()->find($request->checkin_id);
            if ($checkin) {
                if ($checkin->workOrder) {
                    return redirect()->route('work-orders.show', $checkin->workOrder);
                }

                return view('work-orders.create', compact('checkin'));
            }
        }

        // If no checkin, we are scheduling a new job from scratch
        // Load limited set of customers for initial dropdown - AJAX search available for more
        $customers = \App\Models\Customer::orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->sortBy('name'); // Sort alphabetically for display

        $technicians = \App\Models\User::where('is_active', true)->orderBy('name')->get();
        $busy_technician_ids = $this->getBusyTechnicianIds();

        return view('work-orders.create', compact('customers', 'technicians', 'busy_technician_ids'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', WorkOrder::class);

        // Handle "Schedule Job" form submission
        $validated = $request->validate([
            'customer_id' => ['required', TenantValidation::exists('customers')],
            'vehicle_id' => ['required', TenantValidation::exists('vehicles')],
            'scheduled_at' => 'required|date',
            'estimated_minutes' => 'nullable|integer',
            'service_bay' => 'nullable|integer|between:1,6',
            'service_description' => 'required|string',
            'technician_id' => ['nullable', TenantValidation::exists('users')],
        ]);

        // Check technician availability before assigning
        if (! empty($validated['technician_id']) && $this->isTechnicianBusy($validated['technician_id'])) {
            return back()->withInput()->with('error', 'Selected technician is currently busy with another job.');
        }

        // B-03: prevent double-booking on service bay / technician for the
        // same time slot.
        $conflict = app(\App\Services\WorkOrderService::class)->findScheduleConflict(
            start: \Illuminate\Support\Carbon::parse($validated['scheduled_at']),
            estimatedMinutes: (int) ($validated['estimated_minutes'] ?? 60),
            technicianId: $validated['technician_id'] ?? null,
            serviceBay: isset($validated['service_bay']) ? (int) $validated['service_bay'] : null,
        );
        if ($conflict !== null) {
            return back()->withInput()->with('error', $conflict);
        }

        // B-01: enforce monthly work-order quota for BASIC plan tenants.
        \App\Support\PlanQuota::assertCanCreateWorkOrder();

        $workOrder = WorkOrder::create([
            'tenant_id' => tenant_id(),
            'checkin_id' => null, // Standalone schedule
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'status' => WorkOrderStatus::Scheduled->value,
            'scheduled_at' => $validated['scheduled_at'],
            'estimated_minutes' => $validated['estimated_minutes'],
            'service_bay' => $validated['service_bay'],
            'customer_issues' => $validated['service_description'],
            'technician_id' => $validated['technician_id'],
        ]);

        // Track workorder created event
        app(\App\Services\EventTracker::class)->trackSimple('workorder_created');

        return redirect()->route('dashboard')
            ->with('success', 'Work Order scheduled successfully.');
    }

    /**
     * Quick generate from Checkin List.
     */
    public function generate(Checkin $checkin)
    {
        $existing = WorkOrder::where('checkin_id', $checkin->id)->first();
        if ($existing) {
            return redirect()->route('work-orders.show', $existing);
        }

        // Convert checkin services description/types to initial task list
        $tasks = [];
        $partsUsed = [];
        if ($checkin->service_type) {
            $serviceNames = explode(', ', $checkin->service_type);
            foreach ($serviceNames as $name) {
                $service = \App\Models\Service::with('products')->where('name', trim($name))->first();
                $tasks[] = [
                    'name' => ucfirst(str_replace('_', ' ', trim($name))),
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

        // B-01: enforce monthly work-order quota for BASIC plan tenants.
        \App\Support\PlanQuota::assertCanCreateWorkOrder();

        $workOrder = WorkOrder::create([
            'tenant_id' => $checkin->tenant_id,
            'checkin_id' => $checkin->id,
            'customer_id' => $checkin->customer_id,
            'vehicle_id' => $checkin->vehicle_id,
            'status' => WorkOrderStatus::Created->value,
            'customer_issues' => $checkin->service_description,
            'service_tasks' => $tasks,
            'parts_used' => $partsUsed,
            'technician_id' => auth()->id(),
            'service_bay' => $checkin->service_bay, // Copy bay from checkin
        ]);

        $checkin->update(['status' => CheckinStatus::InProgress->value]);

        return redirect()->route('work-orders.show', $workOrder);
    }

    public function edit(WorkOrder $workOrder)
    {
        $workOrder->load(['checkin', 'customer', 'vehicle', 'technician']);

        $technicians = User::with([
            'workOrders' => function ($query) {
                $query->where('status', WorkOrderStatus::InProgress->value)->latest();
            },
        ])->get();

        return view('work-orders.edit', compact('workOrder', 'technicians'));
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['checkin', 'customer', 'vehicle', 'technician', 'photos']);
        $technicians = User::where('is_active', true)->get();

        return view('work-orders.show', compact('workOrder', 'technicians'));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder)
    {
        $this->authorize('update', $workOrder);

        // Apply field updates (notes, tasks, parts)
        $this->workOrderService->applyUpdates($workOrder, $request->only([
            'technician_notes', 'service_tasks', 'parts_used',
        ]));

        // Assign technician
        if ($request->filled('technician_id')) {
            $error = $this->workOrderService->assignTechnician($workOrder, $request->technician_id);
            if ($error) {
                return back()->with('error', $error);
            }
        }

        // Handle status changes
        if ($request->has('status')) {
            $newStatus = $request->status;

            // Completion triggers a full workflow (stock deduction, invoice, checkin close)
            if ($newStatus === WorkOrderStatus::Completed->value) {
                return $this->handleCompletion($workOrder);
            }

            $error = $this->workOrderService->changeStatus($workOrder, $newStatus);
            if ($error) {
                return back()->with('error', $error);
            }
        }

        $workOrder->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'workOrder' => $workOrder]);
        }

        return back()->with('success', 'Work Order updated successfully');
    }

    /**
     * Handle work order completion: delegate to service, then redirect appropriately.
     */
    protected function handleCompletion(WorkOrder $workOrder)
    {
        try {
            $this->workOrderService->completeWorkOrder($workOrder);

            $invoice = $workOrder->refresh()->invoice;
            if ($invoice) {
                if (! auth()->user()->can('access finance')) {
                    return redirect()->route('work-orders.index')
                        ->with('success', 'Work Order Completed! Invoice generated.');
                }

                return redirect()->route('finance.index', ['tab' => 'invoices'])
                    ->with('success', "Work Order Completed. Invoice {$invoice->invoice_number} generated.");
            }

            return back()->with('success', 'Work Order completed successfully');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show read-only job details (services and parts without prices).
     * For technicians to see what they completed.
     */
    public function jobDetails(WorkOrder $workOrder)
    {
        $workOrder->load(['customer', 'vehicle', 'technician', 'checkin']);

        return view('work-orders.details', compact('workOrder'));
    }
}
