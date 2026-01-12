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
            if (! $model->tenant_id && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
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
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

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
        return $this->tenant_id === auth()->user()?->tenant_id;
    }

    /**
     * Check if model belongs to specific tenant
     */
    public function isOwnedByTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
