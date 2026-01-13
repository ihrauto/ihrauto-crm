<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\Checkin;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\InvoiceService;
use App\Traits\ChecksTechnicianAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkOrderController extends Controller
{
    use ChecksTechnicianAvailability;

    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Generate an invoice from a Work Order (Manual Action).
     */
    public function generateInvoice(WorkOrder $workOrder)
    {
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
            return back()->with('error', 'Failed to generate invoice: ' . $e->getMessage());
        }
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        $query = WorkOrder::whereNotIn('status', ['completed', 'cancelled'])
            ->with(['vehicle', 'customer', 'technician'])
            ->latest();

        // Filter by ownership if user cannot view all work orders
        if (!$user->can('view all work-orders')) {
            $query->where('technician_id', $user->id);
        }

        if ($request->has('date')) {
            $query->whereDate('scheduled_at', $request->date);
        }

        $active_orders = $query->get();

        $completedQuery = WorkOrder::whereIn('status', ['completed', 'cancelled'])
            ->with(['vehicle', 'customer', 'technician'])
            ->latest('completed_at')
            ->limit(50);

        // Also filter completed orders by ownership
        if (!$user->can('view all work-orders')) {
            $completedQuery->where('technician_id', $user->id);
        }

        $completed_orders = $completedQuery->get();

        return view('work-orders.index', compact('active_orders', 'completed_orders'));
    }

    public function board()
    {
        $technicians = User::with([
            'workOrders' => function ($query) {
                $query->where('status', 'in_progress')->latest();
            },
        ])->get();

        return view('work-orders.board', compact('technicians'));
    }

    public function employeeStats()
    {
        $users = User::all();

        return view('work-orders.employee_stats', compact('users'));
    }

    public function showEmployeeStats(Request $request, User $user)
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = $user->workOrders()->where('status', 'completed');

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
            $checkin = Checkin::find($request->checkin_id);
            if ($checkin) {
                if ($checkin->workOrder) {
                    return redirect()->route('work-orders.show', $checkin->workOrder);
                }

                return view('work-orders.create', compact('checkin'));
            }
        }

        // If no checkin, we are scheduling a new job from scratch
        // We need customers and vehicles for selection
        // Ideally these should be fetched via AJAX for performance, but for now pass all for MVP/small shop
        $customers = \App\Models\Customer::orderBy('name')->get();
        $technicians = \App\Models\User::where('is_active', true)->orderBy('name')->get();
        $busy_technician_ids = $this->getBusyTechnicianIds();

        return view('work-orders.create', compact('customers', 'technicians', 'busy_technician_ids'));
    }

    public function store(Request $request)
    {
        // Handle "Schedule Job" form submission
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'vehicle_id' => 'required|exists:vehicles,id',
            'scheduled_at' => 'required|date',
            'estimated_minutes' => 'nullable|integer',
            'service_bay' => 'nullable|integer|between:1,6',
            'service_description' => 'required|string',
            'technician_id' => 'nullable|exists:users,id',
        ]);

        // Check technician availability before assigning
        if (!empty($validated['technician_id']) && $this->isTechnicianBusy($validated['technician_id'])) {
            return back()->withInput()->with('error', 'Selected technician is currently busy with another job.');
        }

        $workOrder = WorkOrder::create([
            'tenant_id' => auth()->user()->tenant_id, // Use authenticated user's tenant
            'checkin_id' => null, // Standalone schedule
            'customer_id' => $validated['customer_id'],
            'vehicle_id' => $validated['vehicle_id'],
            'status' => 'scheduled',
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

        $workOrder = WorkOrder::create([
            'tenant_id' => $checkin->tenant_id,
            'checkin_id' => $checkin->id,
            'customer_id' => $checkin->customer_id,
            'vehicle_id' => $checkin->vehicle_id,
            'status' => 'created',
            'customer_issues' => $checkin->service_description,
            'service_tasks' => $tasks,
            'parts_used' => $partsUsed,
            'technician_id' => auth()->id(),
            'service_bay' => $checkin->service_bay, // Copy bay from checkin
        ]);

        $checkin->update(['status' => 'in_progress']);

        return redirect()->route('work-orders.show', $workOrder);
    }

    public function edit(WorkOrder $workOrder)
    {
        $workOrder->load(['checkin', 'customer', 'vehicle', 'technician']);

        $technicians = User::with([
            'workOrders' => function ($query) {
                $query->where('status', 'in_progress')->latest();
            },
        ])->get();

        return view('work-orders.edit', compact('workOrder', 'technicians'));
    }

    public function show(WorkOrder $workOrder)
    {
        $workOrder->load(['checkin', 'customer', 'vehicle', 'technician']);
        $technicians = User::where('is_active', true)->get();

        return view('work-orders.show', compact('workOrder', 'technicians'));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder)
    {
        // Update notes
        if ($request->has('technician_notes')) {
            $workOrder->technician_notes = $request->technician_notes;
        }

        // Update tasks (JSON)
        if ($request->has('service_tasks')) {
            $workOrder->service_tasks = $request->service_tasks;
        }

        // Update parts (JSON)
        if ($request->has('parts_used')) {
            $workOrder->parts_used = $request->parts_used;
        }

        // Assign technician
        if ($request->has('technician_id') && $request->technician_id) {
            $techId = $request->technician_id;

            // Check availability
            $isBusy = WorkOrder::where('technician_id', $techId)
                ->where('status', 'in_progress')
                ->where('id', '!=', $workOrder->id)
                ->exists();

            if ($isBusy) {
                return back()->with('error', 'Technician is currently busy with another active job.');
            }

            $workOrder->technician_id = $techId;
        }

        // Handle status changes
        if ($request->has('status')) {
            $workOrder->status = $request->status;

            if ($request->status === 'in_progress' && !$workOrder->started_at) {
                $workOrder->started_at = now();
            }

            if ($request->status === 'completed' && !$workOrder->completed_at) {
                return $this->completeWorkOrder($workOrder);
            }
        }

        $workOrder->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'workOrder' => $workOrder]);
        }

        return back()->with('success', 'Work Order updated successfully');
    }

    /**
     * Handle work order completion with invoice generation.
     */
    protected function completeWorkOrder(WorkOrder $workOrder)
    {
        try {
            DB::transaction(function () use ($workOrder) {
                $workOrder->save();

                // Process stock deductions
                $this->invoiceService->processStockDeductions($workOrder);

                $workOrder->completed_at = now();

                // Only update checkin if one exists (tire hotel jobs don't have check-ins)
                if ($workOrder->checkin) {
                    $workOrder->checkin->update(['status' => 'completed', 'checkout_time' => now()]);
                }

                $workOrder->save();

                // Auto-Generate Invoice
                $this->invoiceService->createFromWorkOrder($workOrder);
            });

            $invoice = $workOrder->refresh()->invoice;
            if ($invoice) {
                return redirect()->route('finance.index', ['tab' => 'invoices'])
                    ->with('success', "Work Order Completed. Invoice {$invoice->invoice_number} generated.");
            }

            return back()->with('success', 'Work Order completed successfully');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
