<?php

namespace App\Policies;

use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    /**
     * Determine whether the user can view any work orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the work order.
     */
    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id;
    }

    /**
     * Determine whether the user can create work orders.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the work order.
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id) {
            return false;
        }

        return $workOrder->status !== WorkOrderStatus::Completed->value;
    }

    /**
     * Determine whether the user can delete the work order.
     */
    public function delete(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id) {
            return false;
        }

        // Cannot delete work orders with invoices
        return ! $workOrder->invoice;
    }

    /**
     * Determine whether the user can complete the work order.
     */
    public function complete(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id) {
            return false;
        }

        return in_array($workOrder->status, [
            WorkOrderStatus::Created->value,
            WorkOrderStatus::Pending->value,
            WorkOrderStatus::InProgress->value,
        ]);
    }

    /**
     * Determine whether the user can generate an invoice from this work order.
     */
    public function generateInvoice(User $user, WorkOrder $workOrder): bool
    {
        if ($user->tenant_id !== $workOrder->tenant_id) {
            return false;
        }

        return ! $workOrder->invoice && $workOrder->status !== WorkOrderStatus::Cancelled->value;
    }

    /**
     * Determine whether the user can restore the work order.
     */
    public function restore(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the work order.
     */
    public function forceDelete(User $user, WorkOrder $workOrder): bool
    {
        return false;
    }
}
