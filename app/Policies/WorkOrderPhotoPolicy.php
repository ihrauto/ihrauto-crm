<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderPhoto;

class WorkOrderPhotoPolicy
{
    /**
     * Anyone in the same tenant can view the photo.
     */
    public function view(User $user, WorkOrderPhoto $photo): bool
    {
        return $this->sameTenant($user, $photo);
    }

    /**
     * Anyone in the same tenant can upload photos (store is tenant-scoped via the WO).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Delete rules:
     *   - Must be in the same tenant (belt)
     *   - Must be either the original uploader OR an admin/owner (suspenders)
     *   - Photo must not be on a completed/invoiced work order (bracing) — we do not
     *     want technicians wiping audit evidence after the job is closed.
     */
    public function delete(User $user, WorkOrderPhoto $photo): bool
    {
        if (! $this->sameTenant($user, $photo)) {
            return false;
        }

        // DATA-02: refuse delete on already-soft-deleted photo rows. Cleans
        // up any UI that might otherwise offer a second-delete click on a
        // record Eloquent would silently no-op on.
        if ($photo->trashed()) {
            return false;
        }

        // Uploader or admin/owner can delete
        $canDeleteRoleCheck = $photo->user_id === $user->id
            || $user->hasRole(['admin', 'owner']);

        if (! $canDeleteRoleCheck) {
            return false;
        }

        // Block deletion once the parent work order is completed/invoiced.
        $workOrder = $photo->workOrder;
        if ($workOrder && in_array($workOrder->status, ['completed', 'invoiced'], true)) {
            // Only admin/owner can delete after completion (audit override)
            return $user->hasRole(['admin', 'owner']);
        }

        return true;
    }

    /**
     * Both records must be in the current user's tenant.
     *
     * A photo's tenant_id must match both the user's tenant_id AND the
     * current request's tenant context.
     */
    private function sameTenant(User $user, WorkOrderPhoto $photo): bool
    {
        return (int) $user->tenant_id === (int) $photo->tenant_id
            && (int) tenant_id() === (int) $photo->tenant_id;
    }
}
