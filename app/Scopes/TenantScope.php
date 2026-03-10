<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Flag to prevent infinite recursion when auth()->user() triggers another query
     */
    protected static bool $applying = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Prevent infinite recursion
        if (static::$applying) {
            return;
        }

        static::$applying = true;

        try {
            $tenantId = tenant_id();

            if ($tenantId) {
                $builder->where($model->getTable().'.tenant_id', $tenantId);
            }
        } finally {
            static::$applying = false;
        }
    }

    /**
     * Extend the query builder with the needed functions.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenantScope', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        $builder->macro('forTenant', function (Builder $builder, $tenantId) {
            return $builder->withoutGlobalScope($this)->where('tenant_id', $tenantId);
        });

        $builder->macro('forCurrentTenant', function (Builder $builder) {
            $tenantId = tenant_id();

            return $builder->withoutGlobalScope($this)->where('tenant_id', $tenantId);
        });
    }
}
