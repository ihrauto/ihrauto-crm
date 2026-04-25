<?php

namespace App\Policies;

use App\Models\User;

/**
 * Audit-M-9: defense-in-depth policy for User.
 *
 * User-management routes are gated by the `permission:manage users`
 * middleware. This policy adds the per-instance ownership rules:
 *
 *   - super-admin can do anything (no tenant boundary).
 *   - In-tenant users can only see / mutate users in their own tenant.
 *   - Nobody can promote a user past their own assignable role —
 *     that's enforced separately by TenantUserAccess::ensureCanAssignRole,
 *     but the policy view/update/delete check still gates the action.
 *   - Users cannot delete themselves.
 */
class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    public function view(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id
            && $user->can('manage users');
    }

    public function create(User $user): bool
    {
        return $user->can('manage users');
    }

    public function update(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id
            && $user->can('manage users');
    }

    public function delete(User $user, User $target): bool
    {
        // Audit-M-9: prevent self-delete. Otherwise a misclick locks
        // the actor out of their own session and (if they were the
        // last admin) the tenant.
        if ($user->id === $target->id) {
            return false;
        }

        return $user->tenant_id === $target->tenant_id
            && $user->can('manage users')
            && $user->can('delete records');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id
            && $user->can('manage users');
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }
}
