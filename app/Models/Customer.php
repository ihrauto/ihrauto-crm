<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Soft-delete cascade.
     *
     * CustomerController::destroy() is the primary guard — it blocks deletion
     * when dependents exist, forcing explicit resolution by the operator.
     *
     * This boot listener is a secondary safety net for the edge case where
     * a Customer is soft-deleted through a different code path (CLI command,
     * a seeder, or a future bulk-delete feature). It cascades the soft delete
     * to vehicles so orphaned vehicle records don't linger after a customer
     * purge. We only cascade vehicles — invoices, payments, and work orders
     * are intentionally retained for audit/finance continuity.
     */
    protected static function booted(): void
    {
        static::deleting(function (Customer $customer) {
            // Only cascade on soft delete, not force delete (force delete already
            // cascades via DB foreign key constraints).
            if (! $customer->isForceDeleting()) {
                $customer->vehicles()->each(function (Vehicle $vehicle) {
                    if (! $vehicle->trashed()) {
                        $vehicle->delete();
                    }
                });
            }

            // C.9 — invalidate the cached plan-limit count so newly freed slots
            // become immediately visible to Tenant::canAddCustomer().
            if ($customer->tenant_id) {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$customer->tenant_id}_customer_count");
            }
        });

        static::created(function (Customer $customer) {
            if ($customer->tenant_id) {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$customer->tenant_id}_customer_count");
            }
        });

        static::restoring(function (Customer $customer) {
            // When the customer is restored, also restore their vehicles that
            // were soft-deleted at the same time.
            $customer->vehicles()->withTrashed()->restore();
        });

        static::restored(function (Customer $customer) {
            if ($customer->tenant_id) {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$customer->tenant_id}_customer_count");
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'date_of_birth',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_active' => 'boolean',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }

    /**
     * C-03: expose the filtered collections as real HasMany relationships so
     * callers can eager-load them (`Customer::with('activeVehicles')->get()`)
     * instead of triggering a query per customer via the old accessors.
     * The accessors below are kept for backward compatibility but now
     * delegate to the cached relation.
     */
    public function activeVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class)->where('is_active', true);
    }

    public function activeCheckins(): HasMany
    {
        return $this->hasMany(Checkin::class)
            ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function storedTires(): HasMany
    {
        return $this->hasMany(Tire::class)->where('status', 'stored');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Backward-compat accessors. Prefer the HasMany relations above so
     * Eloquent can eager-load and cache the results in a single query.
     */
    public function getActiveVehiclesAttribute()
    {
        return $this->getRelationValue('activeVehicles');
    }

    public function getActiveCheckinsAttribute()
    {
        return $this->getRelationValue('activeCheckins');
    }

    public function getStoredTiresAttribute()
    {
        return $this->getRelationValue('storedTires');
    }
}
