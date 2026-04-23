<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FinanceController extends Controller
{
    public function __construct(
        protected FinanceService $financeService,
    ) {}

    /**
     * Display Finance dashboard with Overview, Payments, Balances, Quotes, and Invoices.
     */
    public function index(Request $request)
    {
        Gate::authorize('view-financials');
        $search = $request->query('search', '');
        $activeTab = $request->query('tab', 'issued');

        $overview = $this->financeService->getOverview();

        // Search scope closure for invoices
        $searchInvoices = function ($query) use ($search) {
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('customer', function ($cq) use ($search) {
                        $cq->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%']);
                    })
                        ->orWhereHas('vehicle', function ($vq) use ($search) {
                            $vq->whereRaw('LOWER(make) LIKE ?', ['%'.strtolower($search).'%'])
                                ->orWhereRaw('LOWER(model) LIKE ?', ['%'.strtolower($search).'%'])
                                ->orWhereRaw('LOWER(license_plate) LIKE ?', ['%'.strtolower($search).'%']);
                        })
                        ->orWhereRaw('LOWER(invoice_number) LIKE ?', ['%'.strtolower($search).'%']);
                });
            }

            return $query;
        };

        // Payments List (PAID tab) - shows payment records
        $paidPaymentsQuery = Payment::with(['invoice.customer']);
        if ($search) {
            $paidPaymentsQuery->whereHas('invoice', function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%']);
                })
                    ->orWhereRaw('LOWER(invoice_number) LIKE ?', ['%'.strtolower($search).'%']);
            });
        }
        $paidPayments = $paidPaymentsQuery->latest('payment_date')
            ->paginate(20, ['*'], 'payments_page');

        // ISSUED tab - Invoices created TODAY with balance > 0
        $issuedQuery = Invoice::with(['customer', 'vehicle'])
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL])
            ->whereDate('created_at', now()->toDateString())
            ->whereRaw('total - paid_amount > 0');
        $searchInvoices($issuedQuery);
        $issuedInvoices = $issuedQuery->latest('created_at')->get();

        // UNPAID tab - All invoices with balance > 0
        $unpaidQuery = Invoice::with(['customer', 'vehicle'])
            ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID, Invoice::STATUS_PAID])
            ->whereRaw('total - paid_amount > 0');
        $searchInvoices($unpaidQuery);
        $unpaidInvoices = $unpaidQuery->orderByRaw('total - paid_amount DESC')->get();

        // All Invoices (ALL tab)
        $allQuery = Invoice::with(['customer', 'vehicle', 'payments']);
        $searchInvoices($allQuery);
        $invoices = $allQuery->latest()
            ->paginate(15, ['*'], 'invoices_page');

        return view('finance.index', compact('overview', 'paidPayments', 'unpaidInvoices', 'issuedInvoices', 'invoices', 'activeTab', 'search'));
    }
}
