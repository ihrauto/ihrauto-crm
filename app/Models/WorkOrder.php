<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; // Assuming this trait exists based on Checkin model

class WorkOrder extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'checkin_id',
        'customer_id',
        'vehicle_id',
        'technician_id',
        'status',
        'service_tasks',
        'customer_issues',
        'technician_notes',
        'parts_used',
        'started_at',
        'completed_at',
        'scheduled_at',
        'estimated_minutes',
        'service_bay',
    ];

    protected $casts = [
        'service_tasks' => 'array',
        'parts_used' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function checkin(): BelongsTo
    {
        return $this->belongsTo(Checkin::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function quote()
    {
        return $this->hasOne(Quote::class);
    }

    // Helper for Status Badge Color
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'created' => 'bg-gray-100 text-gray-800',
            'in_progress' => 'bg-blue-100 text-blue-800 font-bold border border-blue-200',
            'waiting_parts' => 'bg-purple-100 text-purple-800 border-purple-200',
            'completed' => 'bg-green-100 text-green-800 font-bold border border-green-200',
            'cancelled' => 'bg-red-50 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'scheduled' => 'Scheduled',
            'created' => 'Pending',
            'in_progress' => 'In Progress',
            'waiting_parts' => 'Waiting for Parts',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
