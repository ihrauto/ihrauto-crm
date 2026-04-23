<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\WorkOrder;

/**
 * C-07: stock operations extracted from InvoiceService.
 *
 * Separates "what happens to inventory" from "what happens on the
 * invoice". Non-invoice flows (returns, manual adjustments, transfers)
 * can reuse the same guarantees without dragging in invoice domain.
 *
 * Guarantees for `deductForWorkOrder`:
 *   - Idempotent: replaying on the same work_order produces no duplicate
 *     StockMovement rows.
 *   - Atomic: either all parts are deducted or none are (throws).
 *   - Never negative: validates before mutating; throws
 *     InsufficientStockException if any part lacks stock.
 *   - Concurrent-safe: lockForUpdate() on both the idempotency probe and
 *     each product row.
 *
 * MUST be called inside a DB transaction — the caller owns the atomic
 * boundary (completion, void, etc.).
 */
class StockService
{
    /**
     * Deduct stock for every part referenced in a work order's `parts_used`.
     *
     * @throws InsufficientStockException
     */
    public function deductForWorkOrder(WorkOrder $workOrder): void
    {
        if (! $workOrder->parts_used) {
            return;
        }

        // Idempotency probe. lockForUpdate serialises concurrent callers so
        // only the first one proceeds; the rest see the row and return.
        $alreadyDeducted = StockMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $workOrder->id)
            ->where('type', 'sale')
            ->lockForUpdate()
            ->exists();

        if ($alreadyDeducted) {
            return;
        }

        // PASS 1 — validate every part has enough stock, locking each
        // product row so another transaction can't drain it between check
        // and decrement.
        $validated = [];

        foreach ($workOrder->parts_used as $part) {
            if (empty($part['product_id'])) {
                continue;
            }

            $product = Product::lockForUpdate()->find($part['product_id']);
            if (! $product) {
                continue;
            }

            $qty = (int) ($part['qty'] ?? 1);
            if ($qty <= 0) {
                continue;
            }

            if ($product->stock_quantity < $qty) {
                throw new InsufficientStockException(
                    $product->name,
                    (float) $product->stock_quantity,
                    (float) $qty,
                );
            }

            $validated[] = ['product' => $product, 'qty' => $qty];
        }

        // PASS 2 — apply the deductions. Products are locked, stock is
        // pre-validated, so neither loop nor `decrement` can fail partway.
        foreach ($validated as $entry) {
            /** @var Product $product */
            $product = $entry['product'];
            $qty = $entry['qty'];

            $product->decrement('stock_quantity', $qty);

            StockMovement::create([
                'tenant_id' => $workOrder->tenant_id,
                'product_id' => $product->id,
                'quantity' => -$qty,
                'type' => 'sale',
                'user_id' => auth()->id(),
                'reference_type' => WorkOrder::class,
                'reference_id' => $workOrder->id,
                'notes' => "Used in Work Order #{$workOrder->id}",
            ]);
        }
    }

    /**
     * Reverse the deductions — used when voiding an invoice backed by a
     * work order. Not idempotent on its own; the caller (void flow) is
     * expected to run exactly once.
     */
    public function reverseForWorkOrder(WorkOrder $workOrder): void
    {
        if (! $workOrder->parts_used) {
            return;
        }

        foreach ($workOrder->parts_used as $part) {
            if (empty($part['product_id'])) {
                continue;
            }

            $product = Product::find($part['product_id']);
            if (! $product) {
                continue;
            }

            $qty = (int) ($part['qty'] ?? 1);

            $product->increment('stock_quantity', $qty);

            StockMovement::create([
                'tenant_id' => $workOrder->tenant_id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'type' => 'void_reversal',
                'user_id' => auth()->id(),
                'reference_type' => WorkOrder::class,
                'reference_id' => $workOrder->id,
                'notes' => 'Reversed due to voided invoice',
            ]);
        }
    }
}
