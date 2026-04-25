<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ENG-011: every SMS / WhatsApp / email attempt produces a row.
 * Append-only — deletes are blocked by the model boot hook so the
 * audit trail can never be silently scrubbed.
 *
 * Mass-assignment is intentionally minimal — only the SmsService
 * writes here, so we don't need fillable to include sensitive
 * routing fields like `to` / `provider_id`. The service uses
 * `forceCreate` so any future fillable is irrelevant.
 */
class CommunicationLog extends Model
{
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    protected $fillable = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CommunicationLog $log) {
            // Compliance: outbound communications form part of the audit
            // trail (proof of customer notification). Refuse delete so
            // ops can't accidentally — or maliciously — scrub history.
            throw new \LogicException('CommunicationLog rows are append-only.');
        });

        static::updating(function () {
            throw new \LogicException('CommunicationLog rows are append-only — updates are not allowed.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
