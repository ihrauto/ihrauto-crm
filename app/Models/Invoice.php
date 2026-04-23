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
    // see fillable for last_reminder_sent_at (B-14 debounce marker).
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
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'locked_at' => 'datetime',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
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
        return in_array($this->status, [self::STATUS_ISSUED, self::STATUS_PARTIAL, self::STATUS_PAID], true);
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
     *
     * Bug review LOG-01: `paid_amount` is a decimal column. Loose `==` treated
     * `"0"`, `"0.000"`, and `0.004` as equal to 0, which lets us void an
     * invoice that already has a partial payment. Use a strict sub-rappen
     * threshold that matches every other paid-amount check in the codebase
     * (finance aggregations use `> 0.01`).
     */
    public function canBeVoided(): bool
    {
        return $this->isIssued() && ! $this->isVoid() && (float) $this->paid_amount < 0.01;
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

    /**
     * Outstanding balance (total minus paid amount), rounded to 2 decimals.
     *
     * Why the round: subtracting two `decimal:2` cast values can produce
     * binary floats like 99.99999999999999, which then fail strict equality
     * checks and print ugly in the UI. Rounding here is the single canonical
     * place that defines what "balance" means for this invoice.
     */
    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    /**
     * Stable per-invoice secret used to bind a signed URL to this invoice.
     * hash_hmac ties the token to APP_KEY so nothing external can forge
     * one. Including `issued_at` means voiding + reissuing produces a new
     * token, which invalidates any previously sent link.
     */
    public function publicPdfToken(): string
    {
        return hash_hmac(
            'sha256',
            $this->id.'|'.$this->invoice_number.'|'.($this->issued_at?->toIso8601String() ?? ''),
            config('app.key'),
        );
    }

    /**
     * Signed, expiring public URL pointing at the print-ready PDF view.
     * Used in issued-invoice emails so customers can click through
     * without an account. 60-day validity matches typical payment terms.
     */
    public function publicPdfUrl(int $daysValid = 60): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'invoices.public-pdf',
            now()->addDays($daysValid),
            ['token' => $this->publicPdfToken(), 'invoice' => $this->id],
        );
    }

    /**
     * Display label for the finance list. Returns a string that honours
     * the invoice's LIFECYCLE first (draft / void) and falls back to a
     * derived payment state for issued invoices.
     *
     * Previously this returned "unpaid" for draft invoices — technically
     * true (no payment yet), but misleading in the UI: the UNPAID tab
     * filters by the real status column, so a "UNPAID" label on a draft
     * made the invoice look missing from its own tab.
     */
    public function getPaymentStatusAttribute()
    {
        // Lifecycle states always win — a draft is a draft, a void is a void,
        // regardless of payment arithmetic.
        if ($this->status === self::STATUS_DRAFT) {
            return 'draft';
        }

        if ($this->status === self::STATUS_VOID) {
            return 'void';
        }

        // Issued / partial / paid invoices: derive from payments.
        if ($this->paid_amount >= $this->total) {
            return 'paid';
        }
        if ($this->paid_amount > 0) {
            return 'partial';
        }
        /*
         * Bug review LOG-10: compare date-only ends-of-day in the app
         * timezone. `due_date` is cast to `date` (no time component), so
         * `->lt(now())` used to flip to overdue right after midnight UTC
         * — which could be hours before the user's local midnight if the
         * server and tenant are in different time zones. The Swiss fleet
         * runs in Europe/Zurich; the app timezone is set there. Comparing
         * `endOfDay()` means an invoice due today stays "unpaid" (not
         * "overdue") for the whole of today, flipping only after local
         * midnight.
         */
        if ($this->due_date && $this->due_date->copy()->endOfDay()->lt(now())) {
            return 'overdue';
        }

        return 'unpaid';
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-gray-100 text-gray-800',
            self::STATUS_ISSUED => 'bg-blue-100 text-blue-800',
            self::STATUS_PARTIAL => 'bg-amber-100 text-amber-800',
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
     *
     * Bug review LOG-05: Swiss VAT rounding per ESTV commercial practice.
     *
     *   Per VAT rate, we:
     *     1. Sum the NET subtotals (per rate group) WITHOUT rounding
     *     2. Apply the rate once on the summed net (VAT = net × rate)
     *     3. Round the net and VAT subtotals to 2 decimals for display
     *
     *   The previous implementation rounded each line's total to 2 decimals
     *   and then applied tax per-line, which compounds rounding error on
     *   invoices with many small-value lines. Example:
     *     10 lines @ 1.105 CHF:
     *       old: each rounds to 1.11 → sum = 11.10, mismatch with accountant
     *       new: sum = 11.05, tax calc on 11.05, exact match
     *
     *   Line-level totals are still persisted for display/audit, but the
     *   invoice-level subtotal/tax_total/total comes from the aggregated
     *   unrounded figures so the bottom line matches what ESTV expects.
     */
    public function recalculate()
    {
        if (! $this->isEditable()) {
            throw new \App\Exceptions\InvoiceImmutableException(
                "Cannot recalculate issued invoice #{$this->invoice_number}."
            );
        }

        $this->loadMissing('items');

        // Unrounded accumulators per VAT rate. Rate is the dictionary key
        // so 0%, 2.6%, 3.8%, and 8.1% rolls accumulate independently.
        $netByRate = [];

        foreach ($this->items as $item) {
            // Line-level stored total stays at 2-decimal precision — it's
            // what the invoice PDF shows. We recompute from qty × price
            // so a tampered stored total is rewritten to ground truth.
            $lineTotal = round((float) $item->quantity * (float) $item->unit_price, 2);

            if ((float) $item->total !== $lineTotal) {
                $item->total = $lineTotal;
                $item->save();
            }

            // Aggregate NET per rate BEFORE rounding. We use the
            // pre-rounded qty × price value so many small rounding errors
            // don't compound.
            $rate = (float) $item->tax_rate;
            $netByRate[(string) $rate] = ($netByRate[(string) $rate] ?? 0.0)
                + ((float) $item->quantity * (float) $item->unit_price);
        }

        $subtotal = 0.0;
        $taxTotal = 0.0;
        foreach ($netByRate as $rateStr => $netSum) {
            $rate = (float) $rateStr;
            $subtotal += $netSum;
            $taxTotal += $netSum * ($rate / 100.0);
        }

        $this->subtotal = round($subtotal, 2);
        $this->tax_total = round($taxTotal, 2);
        $this->total = round($subtotal + $taxTotal - ($this->discount_total ?? 0), 2);
        $this->save();

        return $this;
    }
}
