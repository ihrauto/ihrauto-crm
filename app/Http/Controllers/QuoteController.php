<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\InvoiceService;

class QuoteController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * B-15: Convert a quote into a draft invoice.
     *
     * Thin controller: all domain logic lives in InvoiceService::createFromQuote.
     * The service is idempotent — calling this endpoint on an already-
     * converted quote returns the existing invoice rather than erroring,
     * so a double-click on the UI button is safe.
     */
    public function convertToInvoice(Quote $quote)
    {
        $this->authorize('convertToInvoice', $quote);

        try {
            $invoice = $this->invoiceService->createFromQuote($quote);
        } catch (\InvalidArgumentException $e) {
            // No quotes.show route yet — bounce back to finance with the
            // message so the operator can act on it.
            return redirect()->route('finance.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice #{$invoice->invoice_number} created from quote.");
    }
}
