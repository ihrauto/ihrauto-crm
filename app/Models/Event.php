<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Get the tenant that owns the event.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user that triggered the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
