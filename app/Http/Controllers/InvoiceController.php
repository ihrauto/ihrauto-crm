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
     * Show the form for editing the specified invoice.
     */
    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        if (!$invoice->isEditable()) {
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
}
