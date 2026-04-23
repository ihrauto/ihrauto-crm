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

        // Scalability B-5: only run the query for the currently-visible
        // tab. Running all four queries on every request burned ~30 DB
        // calls when the operator only ever rendered one. The others get
        // initialised to empty collections so the Blade template's
        // per-tab branches + the DRAFT header badge keep working.
        $paidPayments = collect();
        $issuedInvoices = collect();
        $unpaidInvoices = collect();
        $draftInvoices = collect();
        $invoices = null;

        // Lightweight count for the DRAFT tab header badge — visible on
        // every tab so we always query it, but it's one indexed count.
        $draftCount = Invoice::where('status', Invoice::STATUS_DRAFT)->count();

        switch ($activeTab) {
            case 'paid':
                $paidPaymentsQuery = Payment::with(['invoice.customer']);
                if ($search) {
                    $paidPaymentsQuery->whereHas('invoice', function ($q) use ($search) {
                        $q->whereHas('customer', function ($cq) use ($search) {
                            $cq->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($search).'%']);
                        })->orWhereRaw('LOWER(invoice_number) LIKE ?', ['%'.strtolower($search).'%']);
                    });
                }
                $paidPayments = $paidPaymentsQuery->latest('payment_date')
                    ->paginate(20, ['*'], 'payments_page');
                break;

            case 'unpaid':
                $unpaidQuery = Invoice::with(['customer', 'vehicle'])
                    ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID, Invoice::STATUS_PAID])
                    ->whereRaw('total - paid_amount > 0');
                $searchInvoices($unpaidQuery);
                $unpaidInvoices = $unpaidQuery->orderByRaw('total - paid_amount DESC')->get();
                break;

            case 'draft':
                $draftQuery = Invoice::with(['customer', 'vehicle'])
                    ->where('status', Invoice::STATUS_DRAFT);
                $searchInvoices($draftQuery);
                $draftInvoices = $draftQuery->latest('created_at')->get();
                break;

            case 'all':
                $allQuery = Invoice::with(['customer', 'vehicle', 'payments']);
                $searchInvoices($allQuery);
                $invoices = $allQuery->latest()->paginate(15, ['*'], 'invoices_page');
                break;

            case 'issued':
            default:
                $issuedQuery = Invoice::with(['customer', 'vehicle'])
                    ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIAL])
                    ->whereDate('created_at', now()->toDateString())
                    ->whereRaw('total - paid_amount > 0');
                $searchInvoices($issuedQuery);
                $issuedInvoices = $issuedQuery->latest('created_at')->get();
                break;
        }

        return view('finance.index', compact(
            'overview',
            'paidPayments',
            'unpaidInvoices',
            'issuedInvoices',
            'draftInvoices',
            'draftCount',
            'invoices',
            'activeTab',
            'search',
        ));
    }
}
