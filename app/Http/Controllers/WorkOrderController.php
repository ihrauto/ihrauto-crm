<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderStatus;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\Checkin;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use App\Services\SmsService;
use App\Services\WorkOrderService;
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
     * ENG-011: send the "your car is ready" SMS to the customer.
     * Returns the CommunicationLog row's status so the UI can show a
     * specific outcome — queued / failed / skipped (with the reason).
     */
    public function notifyCustomer(WorkOrder $workOrder, SmsService $sms)
    {
        $this->authorize('view', $workOrder);

        $log = $sms->sendWorkOrderReady($workOrder, auth()->id());

        $messages = [
            \App\Models\CommunicationLog::STATUS_QUEUED => ['success', 'SMS queued for delivery to '.$log->to],
            \App\Models\CommunicationLog::STATUS_FAILED => ['error', 'SMS failed: '.$log->error_message],
            \App\Models\CommunicationLog::STATUS_SKIPPED => ['info', 'SMS not sent: '.$log->error_message],
        ];
        [$type, $message] = $messages[$log->status] ?? ['info', 'SMS attempt logged.'];

        return back()->with($type, $message);
    }

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

    public function store(\App\Http\Requests\ScheduleWorkOrderRequest $request)
    {
        $this->authorize('create', WorkOrder::class);

        $validated = $request->validated();

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
     *
     * Thin controller: all logic lives in WorkOrderService::generateFromCheckin.
     * Returns the existing WO (idempotent) or the freshly-created one.
     */
    public function generate(Checkin $checkin)
    {
        $workOrder = $this->workOrderService->generateFromCheckin($checkin);

        return redirect()->route('work-orders.show', $workOrder);
    }

    public function edit(WorkOrder $workOrder)
    {
        // Audit-C-9: TenantScope keeps cross-tenant work orders out, but a
        // technician can otherwise hit /work-orders/{otherTechId}/edit and
        // read another technician's notes/parts. Funnel through the policy
        // so the same rules `index` enforces apply to deep links too.
        $this->authorize('update', $workOrder);

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
        $this->authorize('view', $workOrder);

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

    /**
     * Bulk-change the status of a set of work orders (e.g. mark 5 "created"
     * jobs "cancelled" at once, or move "in_progress" jobs to
     * "waiting_parts"). Each WO goes through the same validateStatusTransition
     * guard as single-update — illegal transitions fail the whole batch
     * (no partial commits).
     *
     * "completed" is intentionally NOT supported here: completion has real
     * side effects (stock, invoice) that deserve an explicit per-WO flow.
     */
    public function bulkStatus(Request $request)
    {
        $validated = $request->validate([
            'work_order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'work_order_ids.*' => ['integer'],
            'status' => ['required', 'string', 'in:in_progress,waiting_parts,cancelled,scheduled'],
        ]);

        $workOrders = WorkOrder::whereIn('id', $validated['work_order_ids'])->get();

        // Audit-C-10: enforce per-WO authorize so a technician can't
        // bulk-flip every WO in the tenant by hitting this endpoint.
        // The route only had the module middleware before; the policy
        // gates ownership scoping (e.g. technicians can only update
        // their own WOs) which the bulk path was bypassing.
        foreach ($workOrders as $wo) {
            $this->authorize('update', $wo);
        }

        $updated = 0;
        $skipped = 0;
        $failed = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($workOrders, $validated, &$updated, &$skipped, &$failed) {
            foreach ($workOrders as $wo) {
                if ($wo->status === $validated['status']) {
                    $skipped++;

                    continue;
                }

                $error = $this->workOrderService->changeStatus($wo, $validated['status']);
                if ($error !== null) {
                    $failed[] = "#{$wo->id} ({$error})";

                    continue;
                }

                $wo->save();
                $updated++;
            }

            if (! empty($failed)) {
                // Atomicity: any illegal transition aborts the whole batch
                // rather than leaving a mixed-state result.
                throw new \RuntimeException('Illegal transition(s): '.implode('; ', $failed));
            }
        });

        $message = "Updated {$updated} work order(s); skipped {$skipped} already at target status.";

        return back()->with('success', $message);
    }
}
