<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any appointments.
     */
    public function viewAny(User $user): bool
    {
        return true; // Tenant scope handles filtering
    }

    /**
     * Determine whether the user can view the appointment.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    /**
     * Determine whether the user can create appointments.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the appointment.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    /**
     * Determine whether the user can delete the appointment.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        if ($user->tenant_id !== $appointment->tenant_id) {
            return false;
        }

        return $user->can('delete records');
    }

    /**
     * Determine whether the user can restore the appointment.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    /**
     * Determine whether the user can permanently delete the appointment.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }
}
