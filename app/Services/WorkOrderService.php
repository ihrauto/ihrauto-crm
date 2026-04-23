<?php

namespace App\Services;

use App\Enums\CheckinStatus;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class WorkOrderService
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    /**
     * Status transition map — defines which status changes are allowed.
     */
    public const ALLOWED_TRANSITIONS = [
        'created' => ['in_progress', 'scheduled', 'cancelled'],
        'scheduled' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'waiting_parts', 'cancelled'],
        'waiting_parts' => ['in_progress', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Check whether a technician is busy (has an in-progress work order).
     */
    public function isTechnicianBusy(int $technicianId, ?int $excludeWorkOrderId = null): bool
    {
        $query = WorkOrder::where('technician_id', $technicianId)
            ->where('status', WorkOrderStatus::InProgress->value);

        if ($excludeWorkOrderId) {
            $query->where('id', '!=', $excludeWorkOrderId);
        }

        return $query->exists();
    }

    /**
     * B-03: check whether a scheduled slot (technician or bay) is already
     * taken by another active work order.
     *
     * Two slots overlap if A.start < B.end AND A.end > B.start. We approximate
     * the end of a WO from estimated_minutes (defaulting to 60 minutes) since
     * there's no explicit end column yet.
     *
     * Returns a human-readable conflict message or null when the slot is free.
     */
    public function findScheduleConflict(
        \Illuminate\Support\Carbon $start,
        int $estimatedMinutes,
        ?int $technicianId,
        ?int $serviceBay,
        ?int $excludeWorkOrderId = null,
    ): ?string {
        $end = $start->copy()->addMinutes(max($estimatedMinutes, 15));

        $base = WorkOrder::query()
            ->whereNotIn('status', [
                WorkOrderStatus::Completed->value,
                WorkOrderStatus::Cancelled->value,
            ])
            ->whereNotNull('scheduled_at');

        if ($excludeWorkOrderId) {
            $base->where('id', '!=', $excludeWorkOrderId);
        }

        $overlapping = (clone $base)
            ->where('scheduled_at', '<', $end)
            // Compute end-of-other: scheduled_at + coalesce(estimated_minutes, 60) minutes.
            // SQLite and PostgreSQL disagree on interval math; cheaper to load and check in PHP.
            ->get();

        foreach ($overlapping as $other) {
            $otherEnd = $other->scheduled_at->copy()
                ->addMinutes(max((int) ($other->estimated_minutes ?? 60), 15));

            if ($otherEnd->lessThanOrEqualTo($start)) {
                continue; // doesn't actually overlap
            }

            if ($technicianId && (int) $other->technician_id === $technicianId) {
                return 'Technician is already booked at '
                    .$other->scheduled_at->format('M j, H:i').'.';
            }

            if ($serviceBay !== null && (int) $other->service_bay === $serviceBay) {
                return 'Service bay #'.$serviceBay.' is already booked at '
                    .$other->scheduled_at->format('M j, H:i').'.';
            }
        }

        return null;
    }

    /**
     * Validate that a status transition is allowed.
     *
     * @return string|null Error message, or null if valid.
     */
    public function validateStatusTransition(string $currentStatus, string $newStatus): ?string
    {
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed)) {
            return "Cannot change status from '{$currentStatus}' to '{$newStatus}'.";
        }

        return null;
    }

    /**
     * Apply field updates from validated request data.
     */
    public function applyUpdates(WorkOrder $workOrder, array $data): void
    {
        if (array_key_exists('technician_notes', $data)) {
            $workOrder->technician_notes = $data['technician_notes'];
        }

        if (array_key_exists('service_tasks', $data)) {
            $workOrder->service_tasks = $data['service_tasks'];
        }

        if (array_key_exists('parts_used', $data)) {
            $workOrder->parts_used = $data['parts_used'];
        }
    }

    /**
     * Assign a technician if available.
     *
     * @return string|null Error message if technician is busy, null on success.
     */
    public function assignTechnician(WorkOrder $workOrder, int $technicianId): ?string
    {
        if ($this->isTechnicianBusy($technicianId, $workOrder->id)) {
            return 'Technician is currently busy with another active job.';
        }

        $workOrder->technician_id = $technicianId;

        return null;
    }

    /**
     * Handle a status change, including side effects (started_at timestamp).
     * Does NOT handle completion — call completeWorkOrder() for that.
     *
     * @return string|null Error message if transition is invalid.
     */
    public function changeStatus(WorkOrder $workOrder, string $newStatus): ?string
    {
        $error = $this->validateStatusTransition($workOrder->status, $newStatus);
        if ($error) {
            return $error;
        }

        $workOrder->status = $newStatus;

        if ($newStatus === WorkOrderStatus::InProgress->value && ! $workOrder->started_at) {
            $workOrder->started_at = now();
        }

        return null;
    }

    /**
     * Complete a work order: set timestamps, deduct stock, close checkin, generate invoice.
     *
     * Guarantees that completed_at >= started_at. If started_at is null (the work
     * order was completed without ever being marked in_progress), we backfill
     * started_at to now() so the ordering invariant holds and duration reports
     * don't show negative or null times.
     *
     * @throws \InvalidArgumentException if the invariant cannot be satisfied
     */
    public function completeWorkOrder(WorkOrder $workOrder): void
    {
        // C-08: the full completion is one atomic unit (B-02), but the body
        // is now a short pipeline of named steps so each concern is legible
        // and unit-testable in isolation.
        DB::transaction(function () use ($workOrder) {
            /*
             * Bug review LOG-04: guard against concurrent completion.
             *
             * Two mechanics tapping "Complete" on the same WO from two
             * phones could previously both pass an unlocked status check,
             * both call createFromWorkOrder(), and produce two invoices
             * with sequential numbers — the second an orphan referencing
             * the same WO. The lock+re-read serialises them: the second
             * request sees status==completed and bails with a clear error.
             */
            $locked = WorkOrder::query()
                ->withoutGlobalScopes() // completion can be called from jobs outside tenant context
                ->lockForUpdate()
                ->findOrFail($workOrder->id);

            if ($locked->status === WorkOrderStatus::Completed->value) {
                throw new \InvalidArgumentException(
                    "Work order #{$locked->id} is already completed"
                    .($locked->completed_at ? ' (at '.$locked->completed_at->toIso8601String().').' : '.')
                );
            }

            // Re-hydrate the passed-in instance with the locked row's state
            // so downstream steps see current values (e.g. started_at may
            // have been nulled or updated between navigation and submit).
            $workOrder->forceFill($locked->getAttributes())->exists = true;

            $completedAt = now();

            $this->assertCompletionTimestamps($workOrder, $completedAt);
            $this->invoiceService->processStockDeductions($workOrder);
            $this->markCompleted($workOrder, $completedAt);
            $this->closeAssociatedCheckin($workOrder, $completedAt);
            $this->invoiceService->createFromWorkOrder($workOrder);
        });
    }

    /**
     * Invariant: completed_at must not precede started_at. If started_at is
     * null we backfill to the completion time. If it's after completion
     * (clock skew, bad historic data), we throw rather than silently swap.
     */
    protected function assertCompletionTimestamps(WorkOrder $workOrder, \Illuminate\Support\Carbon $completedAt): void
    {
        if ($workOrder->started_at === null) {
            $workOrder->started_at = $completedAt;

            return;
        }

        if ($workOrder->started_at->gt($completedAt)) {
            throw new \InvalidArgumentException(
                "Cannot complete work order: started_at ({$workOrder->started_at->toIso8601String()}) "
                .'is after completed_at ('.$completedAt->toIso8601String().'). '
                .'Check the clock and reopen the work order if needed.'
            );
        }
    }

    protected function markCompleted(WorkOrder $workOrder, \Illuminate\Support\Carbon $completedAt): void
    {
        $workOrder->status = WorkOrderStatus::Completed->value;
        $workOrder->completed_at = $completedAt;
        $workOrder->save();
    }

    protected function closeAssociatedCheckin(WorkOrder $workOrder, \Illuminate\Support\Carbon $completedAt): void
    {
        if (! $workOrder->checkin) {
            return;
        }

        $workOrder->checkin->update([
            'status' => CheckinStatus::Completed->value,
            'checkout_time' => $completedAt,
        ]);
    }

    /**
     * C-01 deep: generate a work order from a check-in using the service
     * catalogue to resolve tasks + parts. Idempotent — a checkin that
     * already has a work order returns the existing one. Runs the plan
     * quota guard and the checkin status update inside a single
     * transaction so the two rows stay consistent.
     */
    public function generateFromCheckin(\App\Models\Checkin $checkin, ?int $technicianId = null): WorkOrder
    {
        $existing = WorkOrder::where('checkin_id', $checkin->id)->first();
        if ($existing) {
            return $existing;
        }

        \App\Support\PlanQuota::assertCanCreateWorkOrder();

        return DB::transaction(function () use ($checkin, $technicianId) {
            [$tasks, $partsUsed] = $this->resolveTasksAndParts($checkin->service_type);

            $workOrder = WorkOrder::create([
                'tenant_id' => $checkin->tenant_id,
                'checkin_id' => $checkin->id,
                'customer_id' => $checkin->customer_id,
                'vehicle_id' => $checkin->vehicle_id,
                'status' => WorkOrderStatus::Created->value,
                'customer_issues' => $checkin->service_description,
                'service_tasks' => $tasks,
                'parts_used' => $partsUsed,
                'technician_id' => $technicianId ?? auth()->id(),
                'service_bay' => $checkin->service_bay,
            ]);

            $checkin->update(['status' => CheckinStatus::InProgress->value]);

            return $workOrder;
        });
    }

    /**
     * Resolve a comma-separated service-name string (from the checkin form)
     * into the [$tasks, $partsUsed] shape WorkOrder expects.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    protected function resolveTasksAndParts(?string $serviceType): array
    {
        $tasks = [];
        $partsUsed = [];

        if (empty($serviceType)) {
            return [$tasks, $partsUsed];
        }

        foreach (explode(',', $serviceType) as $rawName) {
            $name = trim($rawName);
            if ($name === '') {
                continue;
            }

            $service = \App\Models\Service::with('products')->where('name', $name)->first();

            $tasks[] = [
                'name' => $service?->name ?? ucfirst(str_replace('_', ' ', $name)),
                'completed' => false,
                'price' => $service?->price ?? 0,
            ];

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

        return [$tasks, $partsUsed];
    }
}
