<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment records are IMMUTABLE financial transactions.
 *
 * D.12 — previously this model used SoftDeletes, which allowed accountants
 * to "delete" payments. That's the wrong abstraction: financial records must
 * be preserved for audit and tax purposes. To reverse a payment, create a
 * new negative payment (void), not delete the original.
 *
 * SoftDeletes trait removed. `delete()` and `forceDelete()` are overridden
 * below to throw. The `deleted_at` column in the table is kept intentionally
 * as an artifact of the earlier migration — it costs nothing and removing it
 * would require another destructive migration. Future payments never write
 * to it; existing null values remain null.
 */
class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'amount',
        'method',
        'payment_date',
        'transaction_reference',
        'idempotency_key',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    /**
     * D.11 — invoice.paid_amount observer.
     *
     * Previously invoice.paid_amount was maintained by an explicit call to
     * InvoiceService::syncPaymentState() inside PaymentController. That worked
     * but meant any code path writing a Payment outside the controller (seeders,
     * tinker scripts, future background jobs) could leave paid_amount out of
     * sync with the actual payment rows.
     *
     * This observer guarantees paid_amount is always recomputed from the sum
     * of related payments on create/update/delete/restore. The sync is
     * idempotent, so the existing explicit call in PaymentController remains
     * safe (it just becomes a no-op second recompute).
     */
    protected static function booted(): void
    {
        $sync = function (Payment $payment) {
            // Skip when the invoice relation can't be resolved (e.g., FK
            // missing in a test fixture). A broken relation is already an
            // error path that shouldn't trigger side effects.
            if (! $payment->invoice_id) {
                return;
            }

            $invoice = Invoice::withoutGlobalScopes()->find($payment->invoice_id);
            if (! $invoice) {
                return;
            }

            try {
                app(\App\Services\InvoiceService::class)->syncPaymentState($invoice);
            } catch (\Throwable $e) {
                \Log::error('payment_observer_sync_failed', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                report($e);

                // B-06: rethrow so any enclosing DB::transaction() rolls the
                // payment back too. A Payment row with an out-of-sync invoice
                // paid_amount is an accounting bug; better to fail the whole
                // operation and let the user retry. Production mutations
                // happen inside PaymentController::store's transaction, so the
                // rollback is correct.
                throw $e;
            }
        };

        static::saved($sync);
        // D.12 — no deleted/restored hooks: payments are immutable and can't
        // be soft-deleted. delete() throws above.
    }

    /**
     * Payments are immutable. Attempting to delete one throws a clear error
     * so callers know to record a reversing payment instead.
     *
     * Seeders and tests can still wipe payments via `truncate()` or
     * `DB::table('payments')->delete()` — those bypass the model layer.
     */
    public function delete(): bool
    {
        throw new \LogicException(
            'Payments are immutable financial records. Create a reversing payment to void.'
        );
    }

    public function forceDelete(): ?bool
    {
        throw new \LogicException(
            'Payments are immutable financial records. Create a reversing payment to void.'
        );
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
