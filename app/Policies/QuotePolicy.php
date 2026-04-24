<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // TenantScope handles filtering
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quote $quote): bool
    {
        // DATA-02: soft-deleted quotes cannot be edited.
        return $user->tenant_id === $quote->tenant_id && ! $quote->trashed();
    }

    public function delete(User $user, Quote $quote): bool
    {
        if ($user->tenant_id !== $quote->tenant_id) {
            return false;
        }

        if ($quote->trashed()) {
            return false;
        }

        return $user->can(Permission::DELETE_RECORDS);
    }

    /**
     * Convert a quote into an invoice. The operation is financial, so
     * permission is gated by the `view-financials` gate too — but only
     * for the same tenant.
     */
    public function convertToInvoice(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id
            && $user->can(Permission::ACCESS_FINANCE);
    }
}
