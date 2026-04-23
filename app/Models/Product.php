<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'description',
        'price',
        'stock_quantity',
        'min_stock_quantity',
        'is_template',
        'unit',
        'purchase_price',
        'order_number',
        'supplier',
        'status',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest();
    }

    /**
     * B-13: scope to products where stock has dropped at or below the
     * tenant-configured reorder threshold. `min_stock_quantity = 0` means
     * "don't alert" — only products with a positive threshold are eligible.
     */
    public function scopeLowStock($query)
    {
        return $query
            ->whereNotNull('min_stock_quantity')
            ->where('min_stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'min_stock_quantity');
    }

    public function isLowStock(): bool
    {
        return $this->min_stock_quantity !== null
            && $this->min_stock_quantity > 0
            && $this->stock_quantity <= $this->min_stock_quantity;
    }

    /**
     * Services that use this product.
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
