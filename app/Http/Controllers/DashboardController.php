<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        // Redirect super-admin to admin dashboard
        try {
            if (auth()->user()?->hasRole('super-admin')) {
                return redirect()->route('admin.dashboard');
            }
        } catch (\Exception $e) {
            // Roles not set up yet - continue as regular user
        }

        $stats = $this->dashboardService->getStats();
        $recent_activities = $this->dashboardService->getRecentActivities();
        $system_status = $this->dashboardService->getSystemStatus();
        $recent_checkins = $this->dashboardService->getRecentCheckins();
        $tire_operations = $this->dashboardService->getTireOperations();

        // New Operational Data - Weekly Calendar
        $weekStart = request('week_start') ? \Carbon\Carbon::parse(request('week_start')) : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $calendar_events = $this->dashboardService->getCalendarWorkOrders(
            $weekStart,
            $weekEnd,
            request('bay', 'all'),
            request('technician', 'all')
        );

        $todays_schedule = $this->dashboardService->getTodaysSchedule();
        $technician_status = $this->dashboardService->getTechnicianStatus();
        $alerts = $this->dashboardService->getAlerts();

        return view('dashboard', compact(
            'stats',
            'recent_activities',
            'system_status',
            'recent_checkins',
            'tire_operations',
            'calendar_events',
            'todays_schedule',
            'technician_status',
            'alerts',
            'weekStart',
            'weekEnd'
        ));
    }
}
