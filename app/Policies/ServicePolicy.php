<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Determine whether the user can view any services.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the service.
     */
    public function view(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id;
    }

    /**
     * Determine whether the user can create services.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the service.
     */
    public function update(User $user, Service $service): bool
    {
        // DATA-02: soft-deleted services cannot be edited.
        return $user->tenant_id === $service->tenant_id && ! $service->trashed();
    }

    /**
     * Determine whether the user can delete the service.
     */
    public function delete(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id && ! $service->trashed();
    }

    /**
     * Determine whether the user can restore the service.
     */
    public function restore(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the service.
     */
    public function forceDelete(User $user, Service $service): bool
    {
        return false;
    }
}
