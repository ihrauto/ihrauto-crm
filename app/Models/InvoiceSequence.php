<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'year',
        'last_number',
    ];
}
