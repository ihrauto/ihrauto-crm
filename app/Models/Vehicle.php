<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Earliest vehicle year accepted. 1900 is chosen to accommodate legitimate
     * classic vehicles but exclude obviously-invalid input (e.g. year 0 or 1).
     */
    public const MIN_YEAR = 1900;

    /**
     * Maximum future year offset. Set to 1 because car manufacturers release
     * "next model year" vehicles in Q4 of the current calendar year (e.g. 2027
     * models arrive in late 2026 showrooms). A higher value would allow
     * obviously-erroneous input like "2050".
     */
    public const MAX_YEAR_OFFSET = 1;

    /**
     * Return the highest year the app currently accepts.
     */
    public static function maxYear(): int
    {
        return (int) date('Y') + self::MAX_YEAR_OFFSET;
    }

    /**
     * C.9 — invalidate Tenant::canAddVehicle() cache on create/delete/restore
     * so plan-limit enforcement sees the current count immediately.
     */
    protected static function booted(): void
    {
        $flush = function (Vehicle $vehicle) {
            if ($vehicle->tenant_id) {
                \Illuminate\Support\Facades\Cache::forget("tenant_{$vehicle->tenant_id}_vehicle_count");
            }
        };

        static::created($flush);
        static::deleted($flush);
        static::restored($flush);
    }

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'license_plate',
        'make',
        'model',
        'year',
        'color',
        'vin',
        'mileage',
        'fuel_type',
        'transmission',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function tires(): HasMany
    {
        return $this->hasMany(Tire::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->make} {$this->model} ({$this->year})";
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->make} {$this->model} - {$this->license_plate}";
    }

    public function getActiveCheckinsAttribute()
    {
        return $this->checkins()->whereNotIn('status', ['completed', 'cancelled'])->get();
    }

    public function getStoredTiresAttribute()
    {
        return $this->tires()->where('status', 'stored')->get();
    }
}
