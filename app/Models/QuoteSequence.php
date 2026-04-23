<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant quote numbering counter. See also InvoiceSequence — same
 * pattern, same reasoning.
 */
class QuoteSequence extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'year',
        'last_number',
    ];
}
