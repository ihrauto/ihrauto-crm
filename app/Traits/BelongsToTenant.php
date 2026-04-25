<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToTenant()
    {
        // Add global scope to automatically filter by tenant
        static::addGlobalScope(new TenantScope);

        // Automatically set tenant_id when creating new records.
        // Audit follow-up: even though `tenant_id` is in `$fillable` on
        // every model that uses this trait, no controller passes raw
        // user input to mass-assignment (every form-request has an
        // explicit rule list that omits tenant_id). The runtime guards
        // below close the residual mass-assignment risk so a future
        // `Model::create($request->all())` cannot quietly cross-assign:
        //
        //   1. On create: if a tenant_id is supplied AND a tenant context
        //      is bound AND they don't match, refuse. CLI / queued
        //      contexts (no tenant context) are exempt because seeders
        //      and tenant provisioning legitimately set tenant_id directly.
        //   2. On update: tenant_id is treated as immutable. Reassignment
        //      across tenants must use a deliberate raw-DB path.
        static::creating(function ($model) {
            $current = tenant_id();
            if (! $model->tenant_id && $current) {
                $model->tenant_id = $current;

                return;
            }

            // Only enforce the cross-tenant write guard when the request
            // is HTTP-driven (web or API). Console / queue / test contexts
            // legitimately seed rows across tenants — they're not the
            // attack surface (no untrusted input). The HTTP guard catches
            // the realistic scenario: a future `Model::create($request->all())`
            // pulls tenant_id from request input while the authenticated
            // user belongs to a different tenant.
            if (app()->runningInConsole()) {
                return;
            }

            if ($model->tenant_id && $current && (int) $model->tenant_id !== (int) $current) {
                throw new \LogicException(sprintf(
                    'Refusing to create %s with tenant_id=%s while tenant context is %s. '
                    .'Cross-tenant writes must go through a deliberate raw-DB path.',
                    static::class, $model->tenant_id, $current
                ));
            }
        });

        static::updating(function ($model) {
            if (app()->runningInConsole()) {
                return;
            }

            if ($model->isDirty('tenant_id')) {
                $original = $model->getOriginal('tenant_id');
                $new = $model->tenant_id;
                if ($original !== null && (int) $original !== (int) $new) {
                    throw new \LogicException(sprintf(
                        'Refusing to reassign %s#%s tenant_id from %s to %s. tenant_id is immutable.',
                        static::class, $model->getKey() ?? '?', $original, $new
                    ));
                }
            }
        });
    }

    /**
     * AUTHZ-M1 (2026-04-24 review): force `tenant_id` to be cast to int at
     * the Eloquent attribute layer. SQLite under PDO returns BIGINT columns
     * as strings unless an explicit cast is applied, which makes identity
     * comparisons (`$a->tenant_id === $b->tenant_id`) non-deterministic
     * between code paths that load the model differently. Pushing the
     * cast through the trait means every tenant-scoped model gets the
     * same defensive type contract — policies can `===` without guessing
     * whether one side is an `int` and the other a `"1"`.
     */
    public function initializeBelongsToTenant(): void
    {
        $this->mergeCasts(['tenant_id' => 'integer']);
    }

    /**
     * Relationship to tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant(Builder $query, $tenantId = null): Builder
    {
        $tenantId = $tenantId ?? tenant_id();

        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to bypass tenant filtering (use with caution)
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Check if model belongs to current tenant
     */
    public function isOwnedByCurrentTenant(): bool
    {
        return $this->tenant_id === tenant_id();
    }

    /**
     * Check if model belongs to specific tenant
     */
    public function isOwnedByTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
