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
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class)->latest();
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
