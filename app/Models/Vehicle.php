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
