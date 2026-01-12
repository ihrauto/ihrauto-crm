<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tire;

class ReportingService
{
    /**
     * Get all Key Performance Indicators for the management dashboard.
     */
    public function getKPIs(): array
    {
        return [
            'monthly_revenue' => $this->getMonthlyRevenue(),
            'service_completion_rate' => $this->getServiceCompletionRate(),
            'storage_utilization' => $this->getStorageUtilization(),
        ];
    }

    /**
     * Get monthly revenue data with growth comparison.
     */
    public function getMonthlyRevenue(): array
    {
        $currentMonth = Checkin::completed()
            ->whereMonth('checkout_time', now()->month)
            ->whereYear('checkout_time', now()->year)
            ->sum('actual_cost');

        $lastMonth = Checkin::completed()
            ->whereMonth('checkout_time', now()->subMonth()->month)
            ->whereYear('checkout_time', now()->subMonth()->year)
            ->sum('actual_cost');

        $growth = $lastMonth > 0 ? (($currentMonth - $lastMonth) / $lastMonth) * 100 : 0;

        return [
            'current' => $currentMonth,
            'growth' => round($growth, 1),
            'trend' => $growth >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Get service completion rate for the current month.
     */
    public function getServiceCompletionRate(): array
    {
        $totalServices = Checkin::whereMonth('created_at', now()->month)->count();
        $completedServices = Checkin::completed()
            ->whereMonth('checkout_time', now()->month)
            ->count();

        $rate = $totalServices > 0 ? ($completedServices / $totalServices) * 100 : 0;

        return [
            'rate' => round($rate, 1),
            'completed' => $completedServices,
            'total' => $totalServices,
        ];
    }

    /**
     * Get tire storage utilization data.
     */
    public function getStorageUtilization(): array
    {
        $totalCapacity = config('crm.tire_hotel.default_capacity');
        $usedCapacity = Tire::stored()->sum('quantity');
        $utilization = $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0;

        return [
            'percentage' => round($utilization, 1),
            'used' => $usedCapacity,
            'total' => $totalCapacity,
        ];
    }

    /**
     * Get performance metrics for the last 7 days.
     */
    public function getPerformanceMetrics(): array
    {
        $last7Days = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $checkins = Checkin::whereDate('checkin_time', $date)->count();
            $completed = Checkin::whereDate('checkout_time', $date)->count();
            $revenue = Checkin::whereDate('checkout_time', $date)->sum('actual_cost');

            $last7Days[] = [
                'date' => $date->format('M j'),
                'checkins' => $checkins,
                'completed' => $completed,
                'revenue' => $revenue,
            ];
        }

        return $last7Days;
    }

    /**
     * Get customer analytics for the current month.
     */
    public function getCustomerAnalytics(): array
    {
        $totalCustomers = Customer::count();
        $newThisMonth = Customer::whereMonth('created_at', now()->month)->count();
        $activeCustomers = Customer::whereHas('checkins', function ($query) {
            $query->whereMonth('checkin_time', now()->month);
        })->count();

        $topCustomers = Customer::withCount([
            'checkins' => function ($query) {
                $query->whereMonth('checkin_time', now()->month);
            },
        ])
            ->orderBy('checkins_count', 'desc')
            ->take(5)
            ->get();

        return [
            'total' => $totalCustomers,
            'new_this_month' => $newThisMonth,
            'active' => $activeCustomers,
            'top_customers' => $topCustomers,
        ];
    }

    /**
     * Get service analytics for the current month.
     */
    public function getServiceAnalytics(): array
    {
        $serviceTypes = Checkin::selectRaw('service_type, COUNT(*) as count')
            ->whereMonth('checkin_time', now()->month)
            ->groupBy('service_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [ucfirst(str_replace('_', ' ', $item->service_type)) => $item->count];
            });

        $avgServiceTime = Checkin::completed()
            ->whereNotNull('checkout_time')
            ->whereMonth('checkout_time', now()->month)
            ->get()
            ->avg(function ($checkin) {
                return $checkin->checkin_time->diffInMinutes($checkin->checkout_time);
            });

        return [
            'types' => $serviceTypes,
            'efficiency' => [
                'avg_time_minutes' => round($avgServiceTime ?: config('crm.defaults.average_service_time_hours') * 60),
            ],
        ];
    }

    /**
     * Get tire storage analytics.
     */
    public function getTireAnalytics(): array
    {
        $tireStats = [
            'total_stored' => Tire::stored()->sum('quantity'),
            'winter_tires' => Tire::stored()->where('season', 'winter')->sum('quantity'),
            'summer_tires' => Tire::stored()->where('season', 'summer')->sum('quantity'),
            'all_season_tires' => Tire::stored()->where('season', 'all_season')->sum('quantity'),
            'ready_for_pickup' => Tire::where('status', 'ready_pickup')->sum('quantity'),
            'overdue_pickups' => Tire::where('pickup_reminder_date', '<', now())
                ->where('status', 'stored')
                ->sum('quantity'),
        ];

        $storageSections = [];
        $sections = ['A', 'B', 'C', 'D'];
        $sectionCapacity = config('crm.tire_hotel.section_capacity');

        foreach ($sections as $section) {
            $used = Tire::where('storage_location', 'like', $section.'%')
                ->where('status', 'stored')
                ->sum('quantity');

            $storageSections[$section] = [
                'used' => $used,
                'total' => $sectionCapacity,
                'percentage' => $sectionCapacity > 0 ? round(($used / $sectionCapacity) * 100) : 0,
            ];
        }

        return [
            'stats' => $tireStats,
            'storage_sections' => $storageSections,
        ];
    }

    /**
     * Get system alerts based on current state.
     */
    public function getSystemAlerts(): \Illuminate\Support\Collection
    {
        $alerts = [];

        // Check for overdue services
        $overdueServices = Checkin::where('status', 'in_progress')
            ->where('checkin_time', '<', now()->subHours(6))
            ->count();

        if ($overdueServices > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Overdue Services',
                'message' => "{$overdueServices} service(s) taking longer than expected",
                'action' => 'Check service bays',
                'priority' => 'medium',
            ];
        }

        // Check for tire pickup reminders
        $tirePickups = Tire::where('pickup_reminder_date', '<=', now())
            ->where('status', 'stored')
            ->count();

        if ($tirePickups > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Tire Pickup Reminders',
                'message' => "{$tirePickups} tire set(s) ready for customer pickup",
                'action' => 'Contact customers',
                'priority' => 'low',
            ];
        }

        // Check storage capacity
        $storageUsage = $this->getStorageUtilization();
        if ($storageUsage['percentage'] > 90) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Storage Nearly Full',
                'message' => "Tire storage is {$storageUsage['percentage']}% full",
                'action' => 'Consider expansion',
                'priority' => 'medium',
            ];
        }

        // Check for inspection due
        $inspectionsDue = Tire::where('next_inspection_date', '<=', now())
            ->where('status', 'stored')
            ->count();

        if ($inspectionsDue > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Tire Inspections Due',
                'message' => "{$inspectionsDue} tire set(s) need inspection",
                'action' => 'Schedule inspections',
                'priority' => 'low',
            ];
        }

        return collect($alerts);
    }
}
