<?php

namespace App\Support;

use App\Exceptions\PlanQuotaExceededException;
use App\Models\Tenant;

/**
 * Centralized plan-quota gating (B-01).
 *
 * `Tenant::canAddX()` returns a bool and is cheap; wrapping it here ensures
 * every creation path throws the SAME exception with the SAME redirect
 * behaviour instead of each controller inventing its own rejection.
 */
class PlanQuota
{
    public static function assertCanCreateWorkOrder(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? tenant();
        if ($tenant === null) {
            return; // superadmin / console flows have no tenant to gate
        }

        if (! $tenant->canCreateWorkOrder()) {
            throw new PlanQuotaExceededException(
                quota: 'work_orders_monthly',
                limit: $tenant->max_work_orders,
                message: "You have reached your plan's monthly work-order limit."
            );
        }
    }

    public static function assertCanAddCustomer(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? tenant();
        if ($tenant === null) {
            return;
        }

        if (! $tenant->canAddCustomer()) {
            throw new PlanQuotaExceededException(
                quota: 'customers',
                limit: $tenant->max_customers,
                message: "You have reached your plan's customer limit."
            );
        }
    }

    public static function assertCanAddVehicle(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? tenant();
        if ($tenant === null) {
            return;
        }

        if (! $tenant->canAddVehicle()) {
            throw new PlanQuotaExceededException(
                quota: 'vehicles',
                limit: $tenant->max_vehicles,
                message: "You have reached your plan's vehicle limit."
            );
        }
    }

    public static function assertCanAddUser(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? tenant();
        if ($tenant === null) {
            return;
        }

        if (! $tenant->canAddUser()) {
            throw new PlanQuotaExceededException(
                quota: 'users',
                limit: $tenant->max_users,
                message: "You have reached your plan's user limit."
            );
        }
    }
}
