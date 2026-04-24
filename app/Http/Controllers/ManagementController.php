<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;

/**
 * Tenant-level management dashboard. Kept deliberately slim after the
 * 2026-04-24 split: the heavy lifting now lives under
 * `App\Http\Controllers\Management\*` —
 *
 *   - Management\SettingsController   — tenant profile + module toggles
 *   - Management\UserController       — invite / edit / delete tenant users
 *   - Management\ExportController     — customer CSV + JSON backup download
 *
 * Route names are unchanged (`management`, `management.settings`,
 * `management.users.*`, `management.export`, `management.backup`,
 * `management.reports`).
 */
class ManagementController extends Controller
{
    public function __construct(
        protected readonly ReportingService $reportingService,
    ) {}

    public function index()
    {
        $kpis = $this->reportingService->getKPIs();
        $performance = $this->reportingService->getPerformanceMetrics();
        $customer_analytics = $this->reportingService->getCustomerAnalytics();
        $service_analytics = $this->reportingService->getServiceAnalytics();
        $tire_analytics = $this->reportingService->getTireAnalytics();
        $alerts = $this->reportingService->getSystemAlerts();
        $users = \App\Models\User::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('management', compact(
            'kpis',
            'performance',
            'customer_analytics',
            'service_analytics',
            'tire_analytics',
            'alerts',
            'users'
        ));
    }

    public function reports()
    {
        $kpis = $this->reportingService->getKPIs();
        $performance = $this->reportingService->getPerformanceMetrics();
        $customer_analytics = $this->reportingService->getCustomerAnalytics();
        $tire_analytics = $this->reportingService->getTireAnalytics();
        $alerts = $this->reportingService->getSystemAlerts();

        return view('management.reports', compact(
            'kpis',
            'performance',
            'customer_analytics',
            'tire_analytics',
            'alerts'
        ));
    }

    public function analytics()
    {
        return view('management.analytics');
    }

    public function notifications()
    {
        abort(404);
    }

    public function pricing()
    {
        return redirect()->route('billing.pricing');
    }
}
