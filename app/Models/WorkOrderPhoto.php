<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WorkOrderPhoto extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'user_id',
        'filename',
        'original_name',
        'path',
        'type',
        'caption',
    ];

    /**
     * Get the work order this photo belongs to.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the user who uploaded this photo.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the full URL for the photo.
     */
    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    /**
     * Check if this is a "before" photo.
     */
    public function isBeforePhoto(): bool
    {
        return $this->type === 'before';
    }

    /**
     * Check if this is an "after" photo.
     */
    public function isAfterPhoto(): bool
    {
        return $this->type === 'after';
    }
}
