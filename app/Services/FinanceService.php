<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\CachedQuery;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    /**
     * Get financial overview stats (revenue, unpaid, overdue).
     */
    public function getOverview(): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember("finance_overview_{$tenantId}", 300, function () {
            return [
                'revenue_month' => Payment::whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount'),
                'revenue_year' => Payment::whereYear('payment_date', now()->year)
                    ->sum('amount'),
                'unpaid_total' => Invoice::where('status', '!=', Invoice::STATUS_VOID)
                    ->whereRaw('total - paid_amount > 0')
                    ->sum(\DB::raw('total - paid_amount')),
                'overdue_total' => Invoice::whereDate('due_date', '<', now())
                    ->whereNotIn('status', [Invoice::STATUS_DRAFT, Invoice::STATUS_VOID, Invoice::STATUS_PAID])
                    ->sum(DB::raw('total - paid_amount')),
            ];
        });
    }

    /**
     * Monthly revenue for the last 12 months (for charts).
     *
     * @return array<int, array{month: string, revenue: float}>
     */
    public function getMonthlyRevenue(int $months = 12): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember("finance_monthly_revenue_{$tenantId}", 300, function () use ($months) {
            $results = Payment::selectRaw("
                    TO_CHAR(payment_date, 'YYYY-MM') as month,
                    SUM(amount) as revenue
                ")
                ->where('payment_date', '>=', now()->subMonths($months)->startOfMonth())
                ->groupByRaw("TO_CHAR(payment_date, 'YYYY-MM')")
                ->orderByRaw("TO_CHAR(payment_date, 'YYYY-MM')")
                ->get();

            // Fill in missing months with 0
            $data = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $monthKey = now()->subMonths($i)->format('Y-m');
                $label = now()->subMonths($i)->format('M Y');
                $found = $results->firstWhere('month', $monthKey);
                $data[] = [
                    'month' => $label,
                    'revenue' => $found ? (float) $found->revenue : 0,
                ];
            }

            return $data;
        });
    }

    /**
     * Payment method breakdown (for pie chart).
     *
     * @return array<int, array{method: string, total: float, count: int}>
     */
    public function getPaymentMethodBreakdown(): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember("finance_payment_methods_{$tenantId}", 300, function () {
            return Payment::selectRaw('method, SUM(amount) as total, COUNT(*) as count')
                ->whereYear('payment_date', now()->year)
                ->groupBy('method')
                ->orderByRaw('SUM(amount) DESC')
                ->get()
                ->map(fn ($row) => [
                    'method' => ucfirst(str_replace('_', ' ', $row->method)),
                    'total' => (float) $row->total,
                    'count' => (int) $row->count,
                ])
                ->toArray();
        });
    }

    /**
     * Top services by revenue (from invoice line items).
     *
     * @return array<int, array{name: string, revenue: float, count: int}>
     */
    public function getTopServices(int $limit = 10): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember("finance_top_services_{$tenantId}", 300, function () use ($limit, $tenantId) {
            // CRITICAL: raw DB::table() bypasses Eloquent global scopes.
            // The tenant_id filter on invoices MUST be explicit here.
            //
            // D.3 — also exclude soft-deleted invoices. Eloquent's SoftDeletes
            // scope is bypassed by raw queries, so trashed rows would otherwise
            // show up in top-service totals.
            return DB::table('invoice_items')
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoices.tenant_id', $tenantId)
                ->whereNull('invoices.deleted_at')
                ->where('invoices.status', '!=', 'void')
                ->selectRaw('invoice_items.description as name, SUM(invoice_items.total) as revenue, COUNT(*) as count')
                ->groupBy('invoice_items.description')
                ->orderByRaw('SUM(invoice_items.total) DESC')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'revenue' => (float) $row->revenue,
                    'count' => (int) $row->count,
                ])
                ->toArray();
        });
    }

    /**
     * Technician productivity stats (completed work orders + revenue).
     *
     * @return array<int, array{name: string, completed: int, revenue: float}>
     */
    public function getTechnicianProductivity(): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember("finance_tech_productivity_{$tenantId}", 300, function () use ($tenantId) {
            // WorkOrder has BelongsToTenant global scope, but defense-in-depth:
            // explicitly filter all joined tables by tenant_id as well.
            return WorkOrder::where('work_orders.tenant_id', $tenantId)
                ->where('work_orders.status', 'completed')
                ->whereNotNull('work_orders.technician_id')
                ->whereYear('work_orders.completed_at', now()->year)
                ->join('users', function ($join) use ($tenantId) {
                    $join->on('work_orders.technician_id', '=', 'users.id')
                        ->where('users.tenant_id', $tenantId);
                })
                ->leftJoin('invoices', function ($join) use ($tenantId) {
                    $join->on('work_orders.id', '=', 'invoices.work_order_id')
                        ->where('invoices.tenant_id', $tenantId)
                        ->whereNull('invoices.deleted_at'); // D.3 — exclude trashed
                })
                ->selectRaw('users.name, COUNT(work_orders.id) as completed, COALESCE(SUM(invoices.total), 0) as revenue')
                ->groupBy('users.name')
                ->orderByRaw('COUNT(work_orders.id) DESC')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'completed' => (int) $row->completed,
                    'revenue' => (float) $row->revenue,
                ])
                ->toArray();
        });
    }
}
