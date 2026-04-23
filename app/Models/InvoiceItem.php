<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'total',
    ];

    protected $casts = [
        // B-05: quantity is stored as INTEGER in Postgres (see migration
        // 2026_04_10_160100_invoice_items_integer_quantity). Casting to
        // decimal:2 here created a type mismatch — tests on SQLite happily
        // accepted 2.5 while Postgres rejected it. Keep the cast in sync
        // with the column type.
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Backfill tenant_id from the parent invoice when it isn't set via
     * tenant_id() (e.g. background jobs, imports). Belt-and-braces next to
     * the BelongsToTenant trait's own creating hook.
     */
    protected static function booted(): void
    {
        static::creating(function (InvoiceItem $item) {
            if (! $item->tenant_id && $item->invoice_id) {
                $parent = Invoice::withoutGlobalScopes()->find($item->invoice_id);
                if ($parent) {
                    $item->tenant_id = $parent->tenant_id;
                }
            }
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
