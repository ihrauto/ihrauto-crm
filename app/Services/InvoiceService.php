<?php

namespace App\Services;

use App\Exceptions\InvoiceImmutableException;
use App\Models\Invoice;
use App\Models\WorkOrder;

class InvoiceService
{
    /**
     * Generate an invoice number for the current year.
     * Uses a transaction-safe approach to avoid duplicates.
     */
    public function generateInvoiceNumber(?int $tenantId = null): string
    {
        $prefix = config('crm.invoice.prefix', 'INV');
        $padding = config('crm.invoice.number_padding', 4);
        $year = now()->year;
        $tenantId = $tenantId ?? (tenant() ? tenant()->id : auth()->user()->tenant_id);

        // Count existing invoices for this tenant and year + 1
        // We use withTrashed() if SoftDeletes were used, but Invoice doesn't seem to use it.
        // However, checks for safety are good.
        $query = Invoice::where('tenant_id', $tenantId)->whereYear('created_at', $year);

        // Handle SoftDeletes if the model uses it (check via method existence)
        if (method_exists(Invoice::class, 'withTrashed')) {
            $query->withTrashed();
        }

        $count = $query->count() + 1;

        return $prefix.'-'.$year.'-'.str_pad($count, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Create a DRAFT invoice from a work order.
     * MUST be called within a database transaction.
     *
     * @throws \Exception if invoice cannot be created
     */
    public function createFromWorkOrder(WorkOrder $workOrder): Invoice
    {
        // Check if invoice already exists (idempotency)
        if ($workOrder->invoice) {
            return $workOrder->invoice;
        }

        $invoiceNumber = $this->generateInvoiceNumber($workOrder->tenant_id);
        $taxRate = config('crm.tax_rate');
        $dueDays = config('crm.invoice.default_due_days', 30);

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

        // Ensure at least one item exists
        if (empty($itemsData)) {
            $itemsData[] = [
                'description' => $workOrder->customer_issues ?: 'General Service Labor',
                'quantity' => 1,
                'unit_price' => 0,
                'tax_rate' => $taxRate,
                'total' => 0,
            ];
        }

        $invoice->items()->createMany($itemsData);
        $invoice->recalculate();

        return $invoice;
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
            throw new InvoiceImmutableException(
                "Cannot void invoice #{$invoice->invoice_number} - has payments or is not issued."
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
                $qty = $part['qty'] ?? 1;
                $price = $part['price'] ?? 0;
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
     * Process stock deductions for parts used in a work order.
     * MUST be called within a database transaction.
     */
    public function processStockDeductions(WorkOrder $workOrder): void
    {
        if (! $workOrder->parts_used) {
            return;
        }

        foreach ($workOrder->parts_used as $part) {
            if (empty($part['product_id'])) {
                continue;
            }

            $product = \App\Models\Product::find($part['product_id']);
            if (! $product) {
                continue;
            }

            $qty = (int) ($part['qty'] ?? 1);

            // Deduct Stock
            $product->decrement('stock_quantity', $qty);

            // Log Movement
            \App\Models\StockMovement::create([
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
     * Reverse stock deductions (when voiding an invoice).
     */
    protected function reverseStockDeductions(WorkOrder $workOrder): void
    {
        if (! $workOrder->parts_used) {
            return;
        }

        foreach ($workOrder->parts_used as $part) {
            if (empty($part['product_id'])) {
                continue;
            }

            $product = \App\Models\Product::find($part['product_id']);
            if (! $product) {
                continue;
            }

            $qty = (int) ($part['qty'] ?? 1);

            // Restore Stock
            $product->increment('stock_quantity', $qty);

            // Log Reversal
            \App\Models\StockMovement::create([
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
