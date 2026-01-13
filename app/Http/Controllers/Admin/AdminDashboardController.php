<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Checkin;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Display the owner/superadmin dashboard with aggregated metrics.
     */
    public function index(): View
    {
        $metrics = $this->buildMetrics();

        return view('admin.dashboard', compact('metrics'));
    }

    /**
     * Build all dashboard metrics.
     */
    private function buildMetrics(): array
    {
        return [
            'health' => $this->getHealthMetrics(),
            'growth' => $this->getGrowthMetrics(),
            'usage' => $this->getUsageMetrics(),
            'risk' => $this->getRiskMetrics(),
        ];
    }

    /**
     * System health metrics.
     */
    private function getHealthMetrics(): array
    {
        return [
            'failed_jobs_count' => DB::table('failed_jobs')->count(),
            'health_check_url' => route('health.check'),
        ];
    }

    /**
     * Growth and adoption metrics.
     */
    private function getGrowthMetrics(): array
    {
        $now = now();

        return [
            // Tenant counts
            'total_tenants' => Tenant::count(),
            'new_tenants_today' => Tenant::whereDate('created_at', $now->toDateString())->count(),
            'new_tenants_7d' => Tenant::where('created_at', '>=', $now->subDays(7))->count(),
            'new_tenants_30d' => Tenant::where('created_at', '>=', $now->copy()->subDays(30))->count(),

            // Active tenants (based on last_seen_at)
            'active_tenants_24h' => Tenant::where('last_seen_at', '>=', now()->subDay())->count(),
            'active_tenants_7d' => Tenant::where('last_seen_at', '>=', now()->subDays(7))->count(),

            // User counts (bypass tenant scope)
            'total_users' => User::withoutTenantScope()->count(),
            'verified_users_count' => User::withoutTenantScope()->whereNotNull('email_verified_at')->count(),
            'verified_users_percentage' => $this->calculateVerifiedPercentage(),
        ];
    }

    /**
     * Calculate percentage of verified users.
     */
    private function calculateVerifiedPercentage(): float
    {
        $total = User::withoutTenantScope()->count();
        if ($total === 0) {
            return 0.0;
        }

        $verified = User::withoutTenantScope()->whereNotNull('email_verified_at')->count();

        return round(($verified / $total) * 100, 1);
    }

    /**
     * Product usage metrics (last 7 days).
     * Uses direct table counts with withoutTenantScope() for reliable cross-tenant data.
     */
    private function getUsageMetrics(): array
    {
        $since = now()->subDays(7);

        return [
            'checkins_7d' => Checkin::withoutTenantScope()
                ->where('created_at', '>=', $since)
                ->count(),
            'workorders_7d' => WorkOrder::withoutTenantScope()
                ->where('created_at', '>=', $since)
                ->count(),
            'invoices_7d' => Invoice::withoutTenantScope()
                ->where('status', Invoice::STATUS_ISSUED)
                ->where('issued_at', '>=', $since)
                ->count(),
            'tirehotel_7d' => Tire::withoutTenantScope()
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * Risk signal metrics.
     */
    private function getRiskMetrics(): array
    {
        $now = now();

        return [
            // Trials expiring in next 7 days
            'trials_expiring_7d' => Tenant::where('is_trial', true)
                ->where('trial_ends_at', '>=', $now)
                ->where('trial_ends_at', '<=', $now->copy()->addDays(7))
                ->count(),

            // Inactive tenants (last_seen_at older than 14 days)
            'inactive_14d' => Tenant::where(function ($query) use ($now) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $now->copy()->subDays(14));
            })->count(),
        ];
    }
}
