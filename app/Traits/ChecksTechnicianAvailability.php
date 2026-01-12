<?php

namespace App\Traits;

use App\Models\WorkOrder;

trait ChecksTechnicianAvailability
{
    /**
     * Check if a technician is currently busy (has an in_progress work order).
     */
    protected function isTechnicianBusy(int $technicianId, ?int $excludeWorkOrderId = null): bool
    {
        $query = WorkOrder::where('technician_id', $technicianId)
            ->where('status', 'in_progress');

        if ($excludeWorkOrderId) {
            $query->where('id', '!=', $excludeWorkOrderId);
        }

        return $query->exists();
    }

    /**
     * Get the first available technician from a list.
     * Returns null if all are busy.
     */
    protected function getAvailableTechnician(array $technicianIds): ?int
    {
        foreach ($technicianIds as $id) {
            if (! $this->isTechnicianBusy($id)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Get all technician IDs that are currently busy.
     */
    protected function getBusyTechnicianIds(): array
    {
        return WorkOrder::where('status', 'in_progress')
            ->whereNotNull('technician_id')
            ->pluck('technician_id')
            ->unique()
            ->toArray();
    }
}
