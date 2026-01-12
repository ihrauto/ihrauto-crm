<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = ['id'];

    public function sections()
    {
        return $this->hasMany(StorageSection::class);
    }
}
