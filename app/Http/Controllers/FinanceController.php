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
        // Overview Stats
        $overview = [
            'revenue_month' => Payment::whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'),
            'revenue_year' => Payment::whereYear('payment_date', now()->year)
                ->sum('amount'),

            // Optimized: Sum directly in DB instead of loading all models
            'outstanding_total' => Invoice::whereNotIn('status', ['draft', 'void'])
                ->sum(\DB::raw('total - paid_amount')),

            'overdue_total' => Invoice::whereDate('due_date', '<', now())
                ->whereNotIn('status', ['draft', 'void', 'paid'])
                ->sum(\DB::raw('total - paid_amount')),
        ];

        // Payments List
        $payments = Payment::with(['invoice.customer'])
            ->latest('payment_date')
            ->paginate(20, ['*'], 'payments_page');

        // Unpaid Invoices (Balances) - Keeping this for the specific "Unpaid" tab/section if needed,
        // or we can just filter the main invoices list.
        // The prompt implies merging everything, so let's keep the existing logic + add the full lists.
        $unpaidInvoices = Invoice::with(['customer'])
            // ->where('status', '!=', 'draft') // Allow paying drafts (effectively activating them)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->filter(fn ($inv) => $inv->balance > 0)
            ->sortByDesc('balance'); // Sort by highest debt

        // All Invoices (from BillingController)
        $invoices = Invoice::with(['customer', 'vehicle', 'payments'])
            ->latest()
            ->paginate(15, ['*'], 'invoices_page');

        $activeTab = $request->query('tab', 'overview');

        return view('finance.index', compact('overview', 'payments', 'unpaidInvoices', 'invoices', 'activeTab'));
    }
}
