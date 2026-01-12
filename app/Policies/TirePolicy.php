<?php

namespace App\Policies;

use App\Models\Tire;
use App\Models\User;

class TirePolicy
{
    /**
     * Determine whether the user can view any tires.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the tire.
     */
    public function view(User $user, Tire $tire): bool
    {
        return $user->tenant_id === $tire->tenant_id;
    }

    /**
     * Determine whether the user can create tires.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the tire.
     */
    public function update(User $user, Tire $tire): bool
    {
        return $user->tenant_id === $tire->tenant_id;
    }

    /**
     * Determine whether the user can delete the tire.
     */
    public function delete(User $user, Tire $tire): bool
    {
        return $user->tenant_id === $tire->tenant_id;
    }

    /**
     * Determine whether the user can mark tire as ready for pickup.
     */
    public function markReadyForPickup(User $user, Tire $tire): bool
    {
        return $user->tenant_id === $tire->tenant_id && $tire->status === 'stored';
    }

    /**
     * Determine whether the user can restore the tire.
     */
    public function restore(User $user, Tire $tire): bool
    {
        return $user->tenant_id === $tire->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the tire.
     */
    public function forceDelete(User $user, Tire $tire): bool
    {
        return false;
    }
}
