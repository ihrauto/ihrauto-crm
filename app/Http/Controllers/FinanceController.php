<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /**
     * Display Finance dashboard with Overview, Payments, Balances, Quotes, and Invoices.
     */
    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $activeTab = $request->query('tab', 'issued');

        // Overview Stats
        $overview = [
            'revenue_month' => Payment::whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'revenue_year' => Payment::whereYear('payment_date', now()->year)
                ->sum('amount'),

            // Sum of all unpaid invoice balances (total - paid_amount where balance > 0)
            'unpaid_total' => Invoice::where('status', '!=', 'cancelled')
                ->whereRaw('total - paid_amount > 0')
                ->sum(\DB::raw('total - paid_amount')),

            'overdue_total' => Invoice::whereDate('due_date', '<', now())
                ->whereNotIn('status', ['draft', 'void', 'paid'])
                ->sum(\DB::raw('total - paid_amount')),
        ];

        // Search scope closure for invoices
        $searchInvoices = function ($query) use ($search) {
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('customer', function ($cq) use ($search) {
                        $cq->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
                    })
                        ->orWhereHas('vehicle', function ($vq) use ($search) {
                            $vq->whereRaw('LOWER(make) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(model) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhereRaw('LOWER(plate_number) LIKE ?', ['%' . strtolower($search) . '%']);
                        })
                        ->orWhereRaw('LOWER(invoice_number) LIKE ?', ['%' . strtolower($search) . '%']);
                });
            }
            return $query;
        };

        // Payments List (PAID tab) - shows payment records
        $paidPaymentsQuery = Payment::with(['invoice.customer']);
        if ($search) {
            $paidPaymentsQuery->whereHas('invoice', function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
                })
                    ->orWhereRaw('LOWER(invoice_number) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }
        $paidPayments = $paidPaymentsQuery->latest('payment_date')
            ->paginate(20, ['*'], 'payments_page');

        // ISSUED tab - Invoices created TODAY with balance > 0
        $issuedQuery = Invoice::with(['customer', 'vehicle'])
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', now()->toDateString());
        $searchInvoices($issuedQuery);
        $issuedInvoices = $issuedQuery->get()
            ->filter(fn($inv) => $inv->balance > 0)
            ->sortByDesc('created_at');

        // UNPAID tab - All invoices with balance > 0
        $unpaidQuery = Invoice::with(['customer', 'vehicle'])
            ->where('status', '!=', 'cancelled');
        $searchInvoices($unpaidQuery);
        $unpaidInvoices = $unpaidQuery->get()
            ->filter(fn($inv) => $inv->balance > 0)
            ->sortByDesc('balance');

        // All Invoices (ALL tab)
        $allQuery = Invoice::with(['customer', 'vehicle', 'payments']);
        $searchInvoices($allQuery);
        $invoices = $allQuery->latest()
            ->paginate(15, ['*'], 'invoices_page');

        return view('finance.index', compact('overview', 'paidPayments', 'unpaidInvoices', 'issuedInvoices', 'invoices', 'activeTab', 'search'));
    }
}
