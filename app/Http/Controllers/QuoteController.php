<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Models\Customer;
use App\Models\Quote;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly QuoteService $quoteService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Quote::class);

        $query = Quote::query()->with(['customer', 'vehicle'])->latest('issue_date');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        $quotes = $query->paginate(15)->withQueryString();

        return view('finance.quotes.index', [
            'quotes' => $quotes,
            'filters' => [
                'status' => $request->string('status')->toString(),
                'q' => $search,
            ],
        ]);
    }

    public function show(Quote $quote)
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'vehicle', 'items', 'invoice']);

        return view('finance.quotes.show', compact('quote'));
    }

    /**
     * Print-first HTML render. Mirrors the invoice PDF endpoint — the
     * browser's "Save as PDF" is the export path. Keeps the stack free
     * of server-side PDF dependencies until we genuinely need server
     * rendering (email attachments, scheduled exports).
     */
    public function pdf(Quote $quote)
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'vehicle', 'items', 'tenant']);

        return response()
            ->view('finance.quotes.pdf', compact('quote'))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('X-Quote-Number', $quote->quote_number);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Quote::class);

        $customers = Customer::query()->where('is_active', true)->orderBy('name')->limit(200)->get();

        return view('finance.quotes.create', compact('customers'));
    }

    public function store(StoreQuoteRequest $request)
    {
        $this->authorize('create', Quote::class);

        $customer = Customer::findOrFail($request->validated()['customer_id']);
        $quote = $this->quoteService->create($request->validated(), $customer);

        return redirect()->route('quotes.show', $quote)
            ->with('success', "Quote #{$quote->quote_number} created.");
    }

    public function edit(Quote $quote)
    {
        $this->authorize('update', $quote);

        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', "Quote #{$quote->quote_number} cannot be edited once it is {$quote->status}.");
        }

        $quote->load('items');
        $customers = Customer::query()->where('is_active', true)->orderBy('name')->limit(200)->get();

        return view('finance.quotes.edit', compact('quote', 'customers'));
    }

    public function update(UpdateQuoteRequest $request, Quote $quote)
    {
        $this->authorize('update', $quote);

        try {
            $this->quoteService->update($quote, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('quotes.show', $quote)
            ->with('success', "Quote #{$quote->quote_number} updated.");
    }

    public function destroy(Quote $quote)
    {
        $this->authorize('delete', $quote);

        // Soft delete; Quote uses SoftDeletes.
        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', "Quote #{$quote->quote_number} deleted.");
    }

    /**
     * B-15: Convert a quote into a draft invoice. Idempotent — a second
     * call on an already-converted quote returns the existing invoice.
     */
    public function convertToInvoice(Quote $quote)
    {
        $this->authorize('convertToInvoice', $quote);

        try {
            $invoice = $this->invoiceService->createFromQuote($quote);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('quotes.show', $quote)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Invoice #{$invoice->invoice_number} created from quote.");
    }
}
