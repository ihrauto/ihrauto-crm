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
        // B-02: wrap the full completion in a single transaction so stock,
        // status, checkin, and invoice creation are one atomic unit. Any
        // throw (InsufficientStockException, invariant violation, DB error)
        // rolls back everything — we never want the WO marked Completed
        // with stock already decremented but the invoice creation failed.
        DB::transaction(function () use ($workOrder) {
            $completedAt = now();

            // Invariant: completed_at must not precede started_at. If the work
            // order was never started, backfill started_at to match. Clock skew
            // or a historical started_at from the future would violate the
            // invariant — we reject it rather than silently swap timestamps.
            if ($workOrder->started_at === null) {
                $workOrder->started_at = $completedAt;
            } elseif ($workOrder->started_at->gt($completedAt)) {
                throw new \InvalidArgumentException(
                    "Cannot complete work order: started_at ({$workOrder->started_at->toIso8601String()}) "
                    .'is after completed_at ('.$completedAt->toIso8601String().'). '
                    .'Check the clock and reopen the work order if needed.'
                );
            }

            // Deduct stock first. processStockDeductions uses a two-pass
            // validate-then-deduct under lockForUpdate, so it throws
            // InsufficientStockException BEFORE mutating any rows. Doing this
            // before the status change gives callers a clean "out of stock"
            // error instead of a "mid-completion rollback" one.
            $this->invoiceService->processStockDeductions($workOrder);

            $workOrder->status = WorkOrderStatus::Completed->value;
            $workOrder->completed_at = $completedAt;
            $workOrder->save();

            if ($workOrder->checkin) {
                $workOrder->checkin->update([
                    'status' => CheckinStatus::Completed->value,
                    'checkout_time' => $completedAt,
                ]);
            }

            $this->invoiceService->createFromWorkOrder($workOrder);
        });
    }
}
