<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'vehicle_id',
        'work_order_id',
        'quote_number',
        'status',
        'issue_date',
        'expiry_date',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder()
    {
        return $this->belongsTo(\App\Models\WorkOrder::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    /**
     * Get the invoice generated from this quote, if any.
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }
}
