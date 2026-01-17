<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'code',
        'description',
        'price',
        'is_active',
        'is_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Products required by this service (Bill of Materials).
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'service_product')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
