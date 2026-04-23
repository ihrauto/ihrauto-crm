<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
            'runtime' => $this->getRuntimeMetrics(),
            'plan_mix' => $this->getPlanMixMetrics(),
            'attention' => $this->getAttentionQueues(),
            'recent_actions' => $this->getRecentActions(),
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
            'refreshed_at' => now(),
        ];
    }

    /**
     * Growth and adoption metrics.
     */
    private function getGrowthMetrics(): array
    {
        $now = now();
        $sevenDaysAgo = $now->copy()->subDays(7);
        $thirtyDaysAgo = $now->copy()->subDays(30);

        return [
            // Tenant counts
            'total_tenants' => Tenant::count(),
            'new_tenants_today' => Tenant::whereDate('created_at', $now->toDateString())->count(),
            'new_tenants_7d' => Tenant::where('created_at', '>=', $sevenDaysAgo)->count(),
            'new_tenants_30d' => Tenant::where('created_at', '>=', $thirtyDaysAgo)->count(),

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
            'suspended_count' => Tenant::where('is_active', false)->count(),
            'expired_count' => Tenant::expired()->count(),
        ];
    }

    /**
     * Runtime configuration metrics for the control center.
     */
    private function getRuntimeMetrics(): array
    {
        return [
            'environment' => app()->environment(),
            'database' => config('database.default'),
            'cache_store' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'mailer' => config('mail.default'),
            'sentry_configured' => filled(config('sentry.dsn')),
        ];
    }

    /**
     * Plan distribution across the tenant base.
     */
    private function getPlanMixMetrics(): array
    {
        return collect(Tenant::ALL_PLANS)->map(function (string $plan) {
            return [
                'plan' => $plan,
                'label' => ucfirst($plan),
                'count' => Tenant::where('plan', $plan)->count(),
            ];
        })->all();
    }

    /**
     * Tenants that most likely need operator attention.
     */
    private function getAttentionQueues(): array
    {
        $now = now();

        return [
            'expiring' => Tenant::query()
                ->where(function ($query) use ($now) {
                    $query->where(function ($trialQuery) use ($now) {
                        $trialQuery->where('is_trial', true)
                            ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)]);
                    })->orWhere(function ($subscriptionQuery) use ($now) {
                        $subscriptionQuery->where('is_trial', false)
                            ->whereBetween('subscription_ends_at', [$now, $now->copy()->addDays(7)]);
                    });
                })
                ->orderByRaw('COALESCE(trial_ends_at, subscription_ends_at) asc')
                ->take(5)
                ->get(),
            'suspended' => Tenant::query()
                ->where('is_active', false)
                ->latest('updated_at')
                ->take(5)
                ->get(),
            'inactive' => Tenant::query()
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '<', $now->copy()->subDays(14))
                ->orderBy('last_seen_at')
                ->take(5)
                ->get(),
        ];
    }

    /**
     * Latest tenant-level administrative actions.
     */
    private function getRecentActions()
    {
        return AuditLog::query()
            ->where('model_type', Tenant::class)
            ->where('action', '!=', 'note')
            ->with('user')
            ->latest()
            ->take(6)
            ->get();
    }
}
