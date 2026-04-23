<?php

namespace App\Services;

use App\Exceptions\InvoiceImmutableException;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        private readonly StockService $stockService = new StockService,
    ) {}

    /**
     * Generate an invoice number for the current year.
     * Uses a transaction-safe approach to avoid duplicates.
     *
     * Bug review LOG-06: enforce that callers run us inside a transaction.
     *
     *   The lockForUpdate() here acquires a row-level lock on the
     *   InvoiceSequence row. WITHOUT an enclosing transaction, Postgres
     *   releases the lock as soon as the SELECT statement ends — meaning
     *   two parallel calls can both read `last_number = N`, both compute
     *   N+1, and both save N+1 to the sequence row. Two invoices get the
     *   same number. The fix is to require callers to wrap the generate +
     *   insert in a transaction; the lock is then held until that
     *   transaction commits, serialising concurrent callers.
     *
     *   Callers already in a transaction include createFromWorkOrder and
     *   createFromQuote (both are wrapped by completeWorkOrder /
     *   convertQuote). We assert the contract here so any new caller that
     *   forgets the transaction fails loudly in tests instead of silently
     *   producing duplicate numbers in production.
     */
    public function generateInvoiceNumber(?int $tenantId = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'InvoiceService::generateInvoiceNumber() must be called inside a '
                .'DB::transaction() so the lockForUpdate on the sequence row is held '
                .'until the invoice row is persisted. Without the enclosing transaction '
                .'the lock is released immediately and concurrent callers can produce '
                .'duplicate invoice numbers.'
            );
        }

        $tenantId = $tenantId ?? tenant_id();
        $tenant = $tenantId ? \App\Models\Tenant::find($tenantId) : null;

        // Per-tenant override: settings.invoice_prefix takes precedence over
        // the platform default so tenants can brand their invoices (e.g.
        // "RE" / "FK" in DE-CH). Falls back to config('crm.invoice.prefix').
        $prefix = ($tenant?->settings['invoice_prefix'] ?? null) ?: config('crm.invoice.prefix', 'INV');
        $padding = config('crm.invoice.number_padding', 4);
        $year = now()->year;

        $sequence = InvoiceSequence::query()
            ->where('tenant_id', $tenantId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                $sequence = InvoiceSequence::create([
                    'tenant_id' => $tenantId,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            } catch (\Throwable) {
                $sequence = InvoiceSequence::query()
                    ->where('tenant_id', $tenantId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }
        }

        $sequence->last_number++;
        $sequence->save();

        return $prefix.'-'.$year.'-'.str_pad((string) $sequence->last_number, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Create a DRAFT invoice from a work order.
     * MUST be called within a database transaction.
     *
     * @throws \Exception if invoice cannot be created
     */
    public function createFromWorkOrder(WorkOrder $workOrder): Invoice
    {
        // Check if invoice already exists (idempotency) — query DB to avoid stale relationship cache
        $existing = Invoice::where('work_order_id', $workOrder->id)->first();
        if ($existing) {
            return $existing;
        }

        $invoiceNumber = $this->generateInvoiceNumber($workOrder->tenant_id);
        // C-05: prefer the tenant's configured tax rate, falling back to the
        // platform default. Keeps Swiss tenants on 8.1% while leaving room
        // for cross-border or Liechtenstein flows later.
        $taxRate = $workOrder->tenant?->taxRate() ?? (float) config('crm.tax_rate', 8.1);
        // Tenants can override default due days via settings JSON.
        $dueDays = (int) ($workOrder->tenant?->settings['default_due_days']
            ?? config('crm.invoice.default_due_days', 30));

        $invoice = Invoice::create([
            'tenant_id' => $workOrder->tenant_id,
            'invoice_number' => $invoiceNumber,
            'work_order_id' => $workOrder->id,
            'customer_id' => $workOrder->customer_id,
            'vehicle_id' => $workOrder->vehicle_id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now(),
            'due_date' => now()->addDays($dueDays),
            'paid_amount' => 0,
            'notes' => "Generated from Work Order #{$workOrder->id}",
            'created_by' => auth()->id(),
        ]);

        $itemsData = $this->buildInvoiceItems($workOrder, $taxRate);

        // Prevent creating zero-value invoices with no real work items
        if (empty($itemsData)) {
            throw new \InvalidArgumentException(
                "Cannot create invoice for Work Order #{$workOrder->id} — no service tasks or parts recorded."
            );
        }

        $invoice->items()->createMany($itemsData);
        $invoice->recalculate();

        return $invoice;
    }

    /**
     * B-15: Convert an accepted quote into a DRAFT invoice.
     *
     * Idempotent: a quote that has already been converted returns the
     * existing invoice rather than creating a duplicate. Wraps the copy in
     * a DB transaction so item creation, quote status change, and link-back
     * either all succeed or all revert.
     *
     * @throws \InvalidArgumentException when the quote isn't in a convertible state
     */
    public function createFromQuote(Quote $quote): Invoice
    {
        // Idempotency FIRST so a second call on an already-converted quote
        // returns the existing invoice rather than tripping the status
        // guard below (the quote's status is now 'converted').
        $existing = Invoice::where('quote_id', $quote->id)->first();
        if ($existing) {
            return $existing;
        }

        if (! in_array($quote->status, ['draft', 'sent', 'accepted'], true)) {
            throw new \InvalidArgumentException(
                "Quote #{$quote->quote_number} cannot be converted from status '{$quote->status}'."
            );
        }

        return DB::transaction(function () use ($quote) {
            // Re-check inside the transaction to avoid a race where two
            // concurrent conversion requests both pass the pre-check.
            $existing = Invoice::where('quote_id', $quote->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $invoiceNumber = $this->generateInvoiceNumber($quote->tenant_id);
            // Tenants can override default due days via settings JSON.
            $dueDays = (int) ($quote->tenant?->settings['default_due_days']
                ?? config('crm.invoice.default_due_days', 30));

            $invoice = Invoice::create([
                'tenant_id' => $quote->tenant_id,
                'invoice_number' => $invoiceNumber,
                'quote_id' => $quote->id,
                'customer_id' => $quote->customer_id,
                'vehicle_id' => $quote->vehicle_id,
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => now(),
                'due_date' => now()->addDays($dueDays),
                'paid_amount' => 0,
                'notes' => "Generated from Quote #{$quote->quote_number}",
                'created_by' => auth()->id(),
            ]);

            $quote->loadMissing('items');

            if ($quote->items->isEmpty()) {
                throw new \InvalidArgumentException(
                    "Cannot convert Quote #{$quote->quote_number} — it has no line items."
                );
            }

            $itemsData = $quote->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'total' => round((int) $item->quantity * (float) $item->unit_price, 2),
            ])->all();

            $invoice->items()->createMany($itemsData);
            $invoice->recalculate();

            // Mark quote as converted so it can't be re-used. `converted`
            // is an accepted status in the 2025 migration; anything else
            // would need a schema change.
            $quote->update(['status' => 'converted']);

            return $invoice->fresh('items');
        });
    }

    /**
     * Issue (finalize) an invoice.
     * Once issued, invoice becomes immutable.
     * MUST be called within a database transaction.
     *
     * @throws InvoiceImmutableException if already issued
     */
    public function issueInvoice(Invoice $invoice): Invoice
    {
        // Idempotency: if already issued, return as-is
        if ($invoice->isIssued()) {
            return $invoice;
        }

        if (! $invoice->isDraft()) {
            throw new InvoiceImmutableException(
                "Cannot issue invoice #{$invoice->invoice_number} - current status: {$invoice->status}"
            );
        }

        // Recalculate totals before finalizing
        $invoice->recalculate();

        // Mark as issued
        $invoice->status = Invoice::STATUS_ISSUED;
        $invoice->issued_at = now();
        $invoice->issued_by = auth()->id();
        $invoice->locked_at = now();
        $invoice->save();

        // Note: Stock deductions are handled in WorkOrderController::completeWorkOrder()
        // to avoid double-deduction. Do NOT call processStockDeductions here.

        return $invoice->fresh();
    }

    /**
     * Void an issued invoice (without deleting).
     * Only unpaid invoices can be voided.
     *
     * @throws InvoiceImmutableException if conditions not met
     */
    public function voidInvoice(Invoice $invoice, string $reason): Invoice
    {
        if ($invoice->isVoid()) {
            return $invoice; // Idempotent
        }

        if (! $invoice->canBeVoided()) {
            // B-11: give the operator a clear path forward. The common failure
            // mode is trying to void an invoice that already received a
            // partial payment — voiding it would leave the customer's money
            // unaccounted for. Force explicit refund first.
            if ((float) $invoice->paid_amount > 0) {
                throw new InvoiceImmutableException(
                    "Cannot void invoice #{$invoice->invoice_number} — it has received "
                    .number_format((float) $invoice->paid_amount, 2).' in payments. '
                    .'Record a reversing (negative) payment first, then void.'
                );
            }

            throw new InvoiceImmutableException(
                "Cannot void invoice #{$invoice->invoice_number} — only issued invoices can be voided."
            );
        }

        $invoice->status = Invoice::STATUS_VOID;
        $invoice->voided_at = now();
        $invoice->voided_by = auth()->id();
        $invoice->void_reason = $reason;
        $invoice->save();

        // Reverse stock deductions if applicable
        if ($invoice->workOrder) {
            $this->reverseStockDeductions($invoice->workOrder);
        }

        return $invoice->fresh();
    }

    /**
     * Mark invoice as paid (update payment status without changing amounts).
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        if ($invoice->isPaid()) {
            return $invoice;
        }

        if (! $invoice->isIssued()) {
            throw new InvoiceImmutableException(
                'Cannot mark draft/void invoice as paid.'
            );
        }

        $invoice->status = Invoice::STATUS_PAID;
        $invoice->save();

        return $invoice->fresh();
    }

    /**
     * Recalculate invoice payment totals and keep the persisted status canonical.
     *
     * Bug review DATA-01:
     *   Previously this was a read-modify-write without a lock. The race was:
     *     T1 reads payments.sum = 100, computes paid_amount=100
     *     T2 reads payments.sum = 120 (its own payment just committed),
     *        computes paid_amount=120, writes paid_amount=120
     *     T1 then writes paid_amount=100  ← overwrites the correct value
     *   Symptom: invoice.paid_amount ≠ sum(payments.amount) after concurrent
     *   writes. No visible error; reconciliation fails silently until a
     *   human notices at year-end.
     *
     *   The fix: wrap the whole read-modify-write in a transaction and lock
     *   the invoice row with SELECT ... FOR UPDATE. Concurrent syncs against
     *   the same invoice now serialize; each one sees the committed state
     *   of the previous sync before recomputing.
     *
     *   This is idempotent under nested transactions — if a caller (e.g.
     *   PaymentController) already holds the lock and is mid-transaction,
     *   re-acquiring the lock in the same transaction is a no-op in Postgres.
     */
    public function syncPaymentState(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            // Re-fetch under FOR UPDATE so the sum we're about to compute
            // and the write we're about to issue are serialized against any
            // other in-flight sync for this invoice.
            $locked = Invoice::query()
                ->withoutGlobalScopes() // observer path can be called outside tenant context
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($locked->isVoid()) {
                return $locked;
            }

            $paidAmount = round((float) $locked->payments()->sum('amount'), 2);

            $locked->paid_amount = $paidAmount;

            if ($locked->status !== Invoice::STATUS_DRAFT) {
                if ($paidAmount >= (float) $locked->total) {
                    // Bug review DATA-22: flag overpayment when paid_amount
                    // clearly exceeds total. A ~1-rappen overage can come
                    // from rounding and is fine; anything bigger is a
                    // refund-owed situation and ops should know about it
                    // so the refund can be processed promptly.
                    if ($paidAmount > ((float) $locked->total) + 0.01) {
                        \Illuminate\Support\Facades\Log::warning('invoice_overpaid', [
                            'invoice_id' => $locked->id,
                            'invoice_number' => $locked->invoice_number,
                            'tenant_id' => $locked->tenant_id,
                            'total' => (float) $locked->total,
                            'paid_amount' => $paidAmount,
                            'overpayment' => $paidAmount - (float) $locked->total,
                        ]);
                    }
                    $locked->status = Invoice::STATUS_PAID;
                } elseif ($paidAmount > 0) {
                    $locked->status = Invoice::STATUS_PARTIAL;
                } else {
                    $locked->status = Invoice::STATUS_ISSUED;
                }
            }

            $locked->save();

            return $locked->fresh();
        });
    }

    /**
     * Update a draft invoice.
     *
     * @throws InvoiceImmutableException if not draft
     */
    public function updateDraftInvoice(Invoice $invoice, array $data): Invoice
    {
        if (! $invoice->isEditable()) {
            throw new InvoiceImmutableException(
                "Cannot update issued invoice #{$invoice->invoice_number}. Only draft invoices can be edited."
            );
        }

        $invoice->update($data);

        return $invoice;
    }

    /**
     * Build invoice line items from work order tasks and parts.
     */
    protected function buildInvoiceItems(WorkOrder $workOrder, float $taxRate): array
    {
        $itemsData = [];

        // Add Service Tasks
        if ($workOrder->service_tasks) {
            foreach ($workOrder->service_tasks as $task) {
                $price = $task['price'] ?? 0;

                // Look up service price if not explicitly set
                if ($price == 0 && ! empty($task['name'])) {
                    $service = \App\Models\Service::where('name', $task['name'])->first();
                    if ($service) {
                        $price = $service->price;
                    }
                }

                $itemsData[] = [
                    'description' => $task['name'] ?? 'Service Task',
                    'quantity' => 1,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'total' => $price,
                ];
            }
        }

        // Add Parts
        if ($workOrder->parts_used) {
            foreach ($workOrder->parts_used as $part) {
                // Quantities must be positive integers. We defensively coerce
                // and clamp so stray float/string input from legacy data
                // doesn't produce weird invoice lines like "2.5 × Oil Filter".
                $qty = max(1, (int) round((float) ($part['qty'] ?? 1)));
                $price = (float) ($part['price'] ?? 0);
                $totalLine = $qty * $price;

                $itemsData[] = [
                    'description' => $part['name'] ?? 'Part',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'tax_rate' => $taxRate,
                    'total' => $totalLine,
                ];
            }
        }

        return $itemsData;
    }

    /**
     * C-07: delegate to StockService. Kept on InvoiceService so existing
     * callers (WorkOrderService, tests, controllers) don't change.
     *
     * @throws \App\Exceptions\InsufficientStockException
     */
    public function processStockDeductions(WorkOrder $workOrder): void
    {
        $this->stockService->deductForWorkOrder($workOrder);
    }

    /**
     * C-07: delegate to StockService. See above.
     */
    protected function reverseStockDeductions(WorkOrder $workOrder): void
    {
        $this->stockService->reverseForWorkOrder($workOrder);
    }
}
