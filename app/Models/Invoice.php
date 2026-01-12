<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // =============================================
    // STATUS CONSTANTS (Single Source of Truth)
    // =============================================
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_VOID = 'void';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_PARTIAL,
        self::STATUS_VOID,
        self::STATUS_PAID,
    ];

    // Fields that are protected once invoice is issued
    protected const IMMUTABLE_FIELDS = [
        'customer_id',
        'vehicle_id',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'issue_date',
        'invoice_number',
    ];

    protected $fillable = [
        'tenant_id',
        'invoice_number',
        'work_order_id',
        'quote_id',
        'customer_id',
        'vehicle_id',
        'status',
        'issue_date',
        'due_date',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'paid_amount',
        'notes',
        'locked_at',
        'created_by',
        'issued_at',
        'issued_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'locked_at' => 'datetime',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    // =============================================
    // BOOT: Model-level immutability guard
    // =============================================
    protected static function boot()
    {
        parent::boot();

        static::updating(function (Invoice $invoice) {
            // If invoice is not draft and immutable fields are being changed, block it
            if (! $invoice->isEditable()) {
                $dirty = $invoice->getDirty();
                $protectedChanges = array_intersect(array_keys($dirty), self::IMMUTABLE_FIELDS);

                if (! empty($protectedChanges)) {
                    throw new \App\Exceptions\InvoiceImmutableException(
                        "Cannot modify issued invoice #{$invoice->invoice_number}. Protected fields: ".implode(', ', $protectedChanges)
                    );
                }
            }
        });

        static::deleting(function (Invoice $invoice) {
            if ($invoice->isIssued() || $invoice->isPaid()) {
                throw new \App\Exceptions\InvoiceImmutableException(
                    "Cannot delete issued/paid invoice #{$invoice->invoice_number}. Use void instead."
                );
            }
        });
    }

    // =============================================
    // STATUS HELPERS
    // =============================================

    /**
     * Check if invoice can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if invoice is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if invoice has been issued (finalized).
     */
    public function isIssued(): bool
    {
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_PAID]);
    }

    /**
     * Check if invoice is void.
     */
    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if invoice can be voided.
     */
    public function canBeVoided(): bool
    {
        return $this->isIssued() && ! $this->isVoid() && $this->paid_amount == 0;
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

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
        return $this->belongsTo(WorkOrder::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function issuedByUser()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function voidedByUser()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // =============================================
    // ACCESSORS
    // =============================================

    public function getBalanceAttribute()
    {
        return $this->total - $this->paid_amount;
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->paid_amount >= $this->total) {
            return 'paid';
        }
        if ($this->paid_amount > 0) {
            return 'partial';
        }
        if ($this->due_date < now() && $this->status !== self::STATUS_DRAFT) {
            return 'overdue';
        }

        return 'unpaid';
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-gray-100 text-gray-800',
            self::STATUS_ISSUED => 'bg-blue-100 text-blue-800',
            self::STATUS_PAID => 'bg-green-100 text-green-800',
            self::STATUS_VOID => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    // =============================================
    // BUSINESS LOGIC
    // =============================================

    /**
     * Recalculate invoice totals.
     * Only allowed for draft invoices.
     */
    public function recalculate()
    {
        if (! $this->isEditable()) {
            throw new \App\Exceptions\InvoiceImmutableException(
                "Cannot recalculate issued invoice #{$this->invoice_number}."
            );
        }

        $this->loadMissing('items');

        $subtotal = 0;
        $taxTotal = 0;

        foreach ($this->items as $item) {
            $subtotal += $item->total;
            $taxTotal += $item->total * ($item->tax_rate / 100);
        }

        $this->subtotal = round($subtotal, 2);
        $this->tax_total = round($taxTotal, 2);
        $this->total = round($subtotal + $taxTotal - ($this->discount_total ?? 0), 2);
        $this->save();

        return $this;
    }
}
