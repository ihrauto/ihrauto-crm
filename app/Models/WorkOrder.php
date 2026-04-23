<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; // Assuming this trait exists based on Checkin model

class WorkOrder extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * C.9 — invalidate the monthly work-order count cache when a WO is created
     * or restored (the BASIC plan caps monthly WOs; operators must see the
     * current remaining count immediately after changes).
     */
    protected static function booted(): void
    {
        $flush = function (WorkOrder $workOrder) {
            if ($workOrder->tenant_id) {
                $month = ($workOrder->created_at ?? now())->format('Y_m');
                \Illuminate\Support\Facades\Cache::forget("tenant_{$workOrder->tenant_id}_wo_month_{$month}");
            }
        };

        static::created($flush);
        static::deleted($flush);
        static::restored($flush);
    }

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

    /**
     * Get all photos attached to this work order.
     */
    public function photos()
    {
        return $this->hasMany(WorkOrderPhoto::class);
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
        $enum = WorkOrderStatus::tryFrom($this->status);

        return $enum ? $enum->label() : ucfirst(str_replace('_', ' ', $this->status));
    }
}
