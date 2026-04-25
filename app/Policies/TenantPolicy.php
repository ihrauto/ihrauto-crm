<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Audit-M-9: defense-in-depth policy for Tenant.
 *
 * The Tenant row is the most sensitive entity in the platform: it
 * holds plan, trial-end-at, IBAN, feature flags, and rate limits.
 * Most of those have no business being writable from the tenant side.
 *
 *   - Super-admin: anything (cross-tenant by definition).
 *   - Tenant admin: view + limited update of THEIR OWN tenant
 *     (contact details, branding) — billing/limits/security require
 *     super-admin or a separate billing-flow service.
 *   - Other roles: view their own tenant only.
 *
 * The "tenant-self-edit safe" subset is tenant-side fields like name,
 * contact, branding. Plan / trial / max_* / features / api_rate_limit
 * are out of band and explicitly NOT covered by `update`.
 */
class TenantPolicy
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
        return false;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function create(User $user): bool
    {
        // Tenants are created via signup (TenantProvisioningService) or
        // the super-admin tenant management surface — neither uses
        // `Gate::authorize('create', Tenant::class)` because both run
        // outside a normal authenticated user context (signup) or are
        // super-admin only (the before() short-circuit). Default deny.
        return false;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id
            && $user->can('manage settings');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        // Tenant deletion is super-admin only; before() already
        // returned true for that role. Anyone else: deny.
        return false;
    }

    public function suspend(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function restore(User $user, Tenant $tenant): bool
    {
        return false;
    }

    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return false;
    }
}
