<?php

namespace App\Policies;

use App\Models\Checkin;
use App\Models\User;

class CheckinPolicy
{
    /**
     * Determine whether the user can view any checkins.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the checkin.
     */
    public function view(User $user, Checkin $checkin): bool
    {
        return $user->tenant_id === $checkin->tenant_id;
    }

    /**
     * Determine whether the user can create checkins.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the checkin.
     */
    public function update(User $user, Checkin $checkin): bool
    {
        return $user->tenant_id === $checkin->tenant_id;
    }

    /**
     * Determine whether the user can delete the checkin.
     */
    public function delete(User $user, Checkin $checkin): bool
    {
        return $user->tenant_id === $checkin->tenant_id;
    }

    /**
     * Determine whether the user can restore the checkin.
     */
    public function restore(User $user, Checkin $checkin): bool
    {
        return $user->tenant_id === $checkin->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the checkin.
     */
    public function forceDelete(User $user, Checkin $checkin): bool
    {
        return false;
    }

    /**
     * Determine whether the user can complete the checkin.
     */
    public function complete(User $user, Checkin $checkin): bool
    {
        return $user->tenant_id === $checkin->tenant_id && $checkin->status !== 'completed';
    }
}
