<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'quote_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'total',
    ];

    protected $casts = [
        // DATA-01 (sprint 2026-04-24): quantity is stored as INTEGER in
        // Postgres — see migration 2026_04_24_140000_quote_items_integer_quantity.
        // Previously cast as decimal:2 which caused silent fraction loss
        // on quote→invoice conversion (InvoiceItem.quantity has always
        // been integer). Kept in lockstep with InvoiceItem.
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Backfill tenant_id from the parent quote when not set by tenant_id()
     * (e.g. background jobs, imports). See InvoiceItem for rationale.
     */
    protected static function booted(): void
    {
        static::creating(function (QuoteItem $item) {
            if (! $item->tenant_id && $item->quote_id) {
                $parent = Quote::withoutGlobalScopes()->find($item->quote_id);
                if ($parent) {
                    $item->tenant_id = $parent->tenant_id;
                }
            }
        });
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }
}
