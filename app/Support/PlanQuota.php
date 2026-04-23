<?php

namespace App\Support;

use App\Exceptions\PlanQuotaExceededException;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Centralized plan-quota gating (B-01).
 *
 * `Tenant::canAddX()` returns a bool and is cheap; wrapping it here ensures
 * every creation path throws the SAME exception with the SAME redirect
 * behaviour instead of each controller inventing its own rejection.
 *
 * Bug review DATA-05 (concurrent overbooking):
 *   The cached `canAddX()` check races under concurrent callers: two
 *   invite-accepts arriving at the same second both read count<limit,
 *   both create users, tenant ends up one seat over quota (revenue leak
 *   on per-seat billing).
 *
 *   When the asserter is called inside a DB transaction we upgrade to a
 *   locked, cache-bypassing check: re-fetch the tenant with FOR UPDATE
 *   and count from the live table. Concurrent callers serialise on the
 *   tenant row, so only one can pass through when count == limit - 1.
 *
 *   When called OUTSIDE a transaction we fall back to the cached check
 *   (preserves existing behaviour for non-critical UI paths that only
 *   use this to hide a "create" button). Business-critical creation
 *   paths should wrap the whole assert + create in `DB::transaction()`.
 */
class PlanQuota
{
    public static function assertCanCreateWorkOrder(?Tenant $tenant = null): void
    {
        $tenant = $tenant ?? tenant();
        if ($tenant === null) {
            return; // superadmin / console flows have no tenant to gate
        }

        if (self::lockedQuotaExceeded($tenant, 'canCreateWorkOrder')) {
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

        if (self::lockedQuotaExceeded($tenant, 'canAddCustomer')) {
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

        if (self::lockedQuotaExceeded($tenant, 'canAddVehicle')) {
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

        if (self::lockedQuotaExceeded($tenant, 'canAddUser')) {
            throw new PlanQuotaExceededException(
                quota: 'users',
                limit: $tenant->max_users,
                message: "You have reached your plan's user limit."
            );
        }
    }

    /**
     * Return true when the named quota is exceeded.
     *
     * When called inside a transaction: the tenant row is locked with FOR
     * UPDATE and the check reads directly from the live table, bypassing
     * the 60-second cache. Concurrent callers against the same tenant
     * serialise — the second one sees the first one's newly-created row.
     *
     * When called outside a transaction: falls back to the cheap cached
     * bool method (same behaviour as pre-DATA-05).
     */
    private static function lockedQuotaExceeded(Tenant $tenant, string $canMethod): bool
    {
        if (DB::transactionLevel() === 0) {
            // Cheap path for read-only UI callers.
            return ! $tenant->{$canMethod}();
        }

        /** @var Tenant|null $locked */
        $locked = Tenant::query()->lockForUpdate()->find($tenant->id);
        if ($locked === null) {
            // Tenant vanished mid-request — treat as quota exceeded to be safe.
            return true;
        }

        // `canX` methods hit a 60-second cache. Inside a transaction we
        // want the live count, so we temporarily disable the cache by
        // forgetting the memo key before re-checking.
        return ! $locked->{$canMethod}(forceFresh: true);
    }
}
