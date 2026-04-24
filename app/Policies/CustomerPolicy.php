<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return true; // Tenant scope handles filtering
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can create customers.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the customer.
     *
     * DATA-02: refuse update on a soft-deleted record. Even though
     * Eloquent silently no-ops `save()` on a trashed model without
     * restore(), the policy-layer guard is cleaner UX (the UI can hide
     * the Edit button) and closes the bulk-update bypass where a
     * `->update([...])` on a trashed row would still hit the DB.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && ! $customer->trashed();
    }

    /**
     * Determine whether the user can delete the customer.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id && ! $customer->trashed();
    }

    /**
     * Determine whether the user can restore the customer.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the customer.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return false; // Prevent permanent deletion
    }
}
