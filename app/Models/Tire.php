<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tire extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'vehicle_id',
        'brand',
        'model',
        'size',
        'season',
        'quantity',
        'condition',
        'storage_location',
        'storage_date',
        'last_inspection_date',
        'next_inspection_date',
        'tread_depth',
        'status',
        'notes',
        'storage_fee',
        'customer_notified',
        'pickup_reminder_date',
    ];

    protected $casts = [
        'storage_date' => 'date',
        'last_inspection_date' => 'date',
        'next_inspection_date' => 'date',
        'pickup_reminder_date' => 'date',
        'tread_depth' => 'decimal:2',
        'storage_fee' => 'decimal:2',
        'customer_notified' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function getFullDescriptionAttribute(): string
    {
        return "{$this->quantity}x {$this->brand} {$this->model} {$this->size}";
    }

    public function getSeasonBadgeColorAttribute(): string
    {
        return match ($this->season) {
            'winter' => 'bg-blue-100 text-blue-800',
            'summer' => 'bg-yellow-100 text-yellow-800',
            'all_season' => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getConditionBadgeColorAttribute(): string
    {
        return match ($this->condition) {
            'excellent' => 'bg-green-100 text-green-800',
            'good' => 'bg-green-100 text-green-800',
            'fair' => 'bg-yellow-100 text-yellow-800',
            'poor' => 'bg-red-100 text-red-800',
            'needs_replacement' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'stored' => 'bg-green-100 text-green-800',
            'ready_pickup' => 'bg-yellow-100 text-yellow-800',
            'maintenance' => 'bg-blue-100 text-blue-800',
            'disposed' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStorageDurationAttribute(): string
    {
        return $this->storage_date->diffForHumans();
    }

    public function getStorageDaysAttribute(): int
    {
        return $this->storage_date->diffInDays(now());
    }

    public function needsInspection(): bool
    {
        return $this->next_inspection_date && $this->next_inspection_date->isPast();
    }

    public function isOverdue(): bool
    {
        return $this->pickup_reminder_date && $this->pickup_reminder_date->isPast();
    }

    public function scopeStored($query)
    {
        return $query->where('status', 'stored');
    }

    public function scopeReadyForPickup($query)
    {
        return $query->where('status', 'ready_pickup');
    }

    public function scopeWinterTires($query)
    {
        return $query->where('season', 'winter');
    }

    public function scopeSummerTires($query)
    {
        return $query->where('season', 'summer');
    }

    public function scopeAllSeasonTires($query)
    {
        return $query->where('season', 'all_season');
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('storage_location', 'like', $location.'%');
    }
}
