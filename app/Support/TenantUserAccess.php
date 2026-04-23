<?php

namespace App\Support;

use App\Models\User;

class TenantUserAccess
{
    private const TENANT_SAFE_ROLES = [
        'admin',
        'manager',
        'technician',
        'receptionist',
    ];

    private const MANAGER_ASSIGNABLE_ROLES = [
        'technician',
        'receptionist',
    ];

    public function tenantSafeRoles(): array
    {
        return self::TENANT_SAFE_ROLES;
    }

    public function assignableRolesFor(User $actor): array
    {
        if ($actor->hasRole('admin')) {
            return self::TENANT_SAFE_ROLES;
        }

        if ($actor->hasRole('manager')) {
            return self::MANAGER_ASSIGNABLE_ROLES;
        }

        return [];
    }

    public function ensureCanAssignRole(User $actor, string $role): void
    {
        abort_unless(
            in_array($role, $this->assignableRolesFor($actor), true),
            403,
            'You are not allowed to assign that role.'
        );
    }

    public function ensureCanManageUser(User $actor, User $target): void
    {
        abort_unless($this->canManageUser($actor, $target), 403, 'You are not allowed to manage that user.');
    }

    public function ensureCanDeleteUser(User $actor, User $target): void
    {
        $this->ensureCanManageUser($actor, $target);

        abort_if($actor->is($target), 403, 'You cannot delete your own account.');
        abort_if($this->isLastActiveTenantAdmin($target), 403, 'You cannot delete the last active tenant admin.');
    }

    public function ensureCanTransitionUserRole(User $actor, User $target, string $newRole): void
    {
        $this->ensureCanManageUser($actor, $target);
        $this->ensureCanAssignRole($actor, $newRole);

        if ($this->effectiveRole($target) === 'admin' && $newRole !== 'admin' && $this->isLastActiveTenantAdmin($target)) {
            abort(403, 'You cannot demote the last active tenant admin.');
        }
    }

    public function ensureMechanicTarget(User $actor, User $target): void
    {
        $this->ensureCanManageUser($actor, $target);

        abort_unless($this->effectiveRole($target) === 'technician', 403, 'Mechanics must be technician users.');
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if (! $actor->tenant_id || ! $target->tenant_id) {
            return false;
        }

        if ((int) $actor->tenant_id !== (int) $target->tenant_id) {
            return false;
        }

        if ($target->hasRole('super-admin')) {
            return false;
        }

        $targetRole = $this->effectiveRole($target);

        if (! $targetRole || ! in_array($targetRole, self::TENANT_SAFE_ROLES, true)) {
            return false;
        }

        return in_array($targetRole, $this->assignableRolesFor($actor), true);
    }

    public function effectiveRole(User $user): ?string
    {
        $roleName = $user->getRoleNames()->first() ?? $user->role;

        if (! is_string($roleName) || $roleName === '') {
            return null;
        }

        return strtolower($roleName);
    }

    public function isLastActiveTenantAdmin(User $user): bool
    {
        if (! $user->tenant_id || $this->effectiveRole($user) !== 'admin' || ! $user->is_active) {
            return false;
        }

        $remainingAdminCount = User::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin'))
            ->count();

        return $remainingAdminCount <= 1;
    }
}
