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

        // Automatically set tenant_id when creating new records
        static::creating(function ($model) {
            if (! $model->tenant_id && tenant_id()) {
                $model->tenant_id = tenant_id();
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
