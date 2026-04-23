<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        // "Paper" view
        $invoice->load(['customer', 'items', 'vehicle', 'payments']);

        return view('finance.invoices.show', compact('invoice'));
    }

    /**
     * Print-ready HTML layout. The browser's own "Save as PDF" is the
     * export path — avoids pulling in a PDF library for a CRUD CRM that
     * mostly just needs to hand operators something printable.
     */
    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'items', 'vehicle', 'payments', 'tenant']);

        return response()
            ->view('finance.invoices.pdf', compact('invoice'))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Invoice-Number', $invoice->invoice_number);
    }

    /**
     * Show the form for editing the specified invoice.
     */
    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if (! $invoice->isEditable()) {
            return redirect()->route('invoices.show', $invoice)
                ->with('error', 'This invoice is locked and cannot be edited.');
        }

        return view('finance.invoices.edit', compact('invoice'));
    }

    /**
     * Update the specified invoice in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
        ]);

        try {
            $this->invoiceService->updateDraftInvoice($invoice, $validated);

            return back()->with('success', 'Invoice updated.');
        } catch (\App\Exceptions\InvoiceImmutableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Issue (finalize) an invoice.
     */
    public function issue(Invoice $invoice)
    {
        $this->authorize('issue', $invoice);

        try {
            $this->invoiceService->issueInvoice($invoice);

            // Track invoice issued event
            app(\App\Services\EventTracker::class)->trackSimple('invoice_issued');

            return back()->with('success', "Invoice #{$invoice->invoice_number} has been issued.");
        } catch (\App\Exceptions\InvoiceImmutableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void an invoice.
     */
    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('void', $invoice);

        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        try {
            $this->invoiceService->voidInvoice($invoice, $validated['void_reason']);

            return redirect()->route('finance.index', ['tab' => 'invoices'])
                ->with('success', "Invoice #{$invoice->invoice_number} has been voided.");
        } catch (\App\Exceptions\InvoiceImmutableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified invoice from storage.
     */
    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        try {
            $invoice->delete();

            return redirect()->route('finance.index', ['tab' => 'invoices'])
                ->with('success', 'Invoice deleted.');
        } catch (\App\Exceptions\InvoiceImmutableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Bulk-issue a set of draft invoices in one click. Idempotent per
     * invoice — already-issued ones are silently skipped.
     *
     * Each invoice gets its own transaction via issueInvoice(), so a
     * single failure doesn't abort the whole batch. We aggregate
     * per-invoice results and report back with totals + the list of
     * invoice numbers that failed.
     */
    public function bulkIssue(Request $request)
    {
        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1', 'max:200'],
            'invoice_ids.*' => ['integer'],
        ]);

        $invoices = Invoice::whereIn('id', $validated['invoice_ids'])->get();

        $issued = 0;
        $skipped = 0;
        $failed = [];

        foreach ($invoices as $invoice) {
            // Authorize each invoice individually — TenantScope already
            // filtered by tenant, but we still want policy to fire.
            if (! $request->user()->can('issue', $invoice)) {
                $failed[] = $invoice->invoice_number;

                continue;
            }

            try {
                if ($invoice->isIssued() || $invoice->isPaid() || $invoice->isVoid()) {
                    $skipped++;

                    continue;
                }

                \Illuminate\Support\Facades\DB::transaction(
                    fn () => $this->invoiceService->issueInvoice($invoice)
                );
                $issued++;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $invoice->invoice_number;
            }
        }

        $message = "Bulk issue complete: {$issued} issued, {$skipped} skipped";
        if (! empty($failed)) {
            $message .= ', '.count($failed).' failed ('.implode(', ', array_slice($failed, 0, 5))
                .(count($failed) > 5 ? ', …' : '').')';
        }

        return back()->with(empty($failed) ? 'success' : 'warning', $message);
    }
}
