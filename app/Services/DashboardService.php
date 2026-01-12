<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tire;

class DashboardService
{
    /**
     * Get all dashboard statistics.
     */
    public function getStats(): array
    {
        // Calculate growth metrics
        $currentMonthCustomers = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $lastMonthCustomers = Customer::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $customerGrowth = $lastMonthCustomers > 0
            ? round((($currentMonthCustomers - $lastMonthCustomers) / $lastMonthCustomers) * 100, 1)
            : ($currentMonthCustomers > 0 ? 100 : 0);

        $currentMonthRevenue = \App\Models\Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $lastMonthRevenue = \App\Models\Payment::whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');
        $revenueGrowth = $lastMonthRevenue > 0
            ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($currentMonthRevenue > 0 ? 100 : 0);

        // Work order statistics for Option A dashboard
        $activeJobs = \App\Models\WorkOrder::where('status', 'in_progress')->count();
        $pendingJobs = \App\Models\WorkOrder::where('status', 'created')->count();
        $completedToday = \App\Models\WorkOrder::where('status', 'completed')
            ->whereDate('updated_at', today())
            ->count();

        $longRunningJobs = \App\Models\WorkOrder::where('status', 'in_progress')
            ->where('started_at', '<', now()->subHours(4))
            ->count();

        // Secondary Stats (Option B)
        // Appointments = scheduled for this week (upcoming)
        $appointmentsThisWeek = \App\Models\Appointment::whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereNotIn('status', ['completed', 'cancelled', 'failed'])
            ->count();

        $lowStockCount = \App\Models\Product::whereColumn('stock_quantity', '<=', 'min_stock_quantity')->count();
        $totalBays = config('crm.service_bays.count', 6);
        $freeBays = max(0, $totalBays - $activeJobs);

        // Technicians available (All active users - those with active jobs)
        $activeTechnicianIds = \App\Models\WorkOrder::where('status', 'in_progress')
            ->whereNotNull('technician_id')
            ->pluck('technician_id')
            ->unique();

        $idleTechnicians = \App\Models\User::where('is_active', true)
            ->whereNotIn('id', $activeTechnicianIds)
            ->count();

        return [
            'total_customers' => Customer::where('is_active', true)->count(),
            'active_checkins' => Checkin::active()->count(),
            'tires_in_hotel' => Tire::stored()->sum('quantity'),

            // Work Order Stats (Option A)
            'active_jobs' => $activeJobs,
            'pending_jobs' => $pendingJobs,
            'completed_today' => $completedToday,
            'long_running_jobs' => $longRunningJobs,

            // Secondary Stats
            'appointments_today' => $appointmentsThisWeek,
            'low_stock_count' => $lowStockCount,
            'free_bays' => $freeBays,
            'idle_technicians' => $idleTechnicians,

            // Financials
            'monthly_revenue' => $currentMonthRevenue,

            // Overdue count (optimized DB query)
            'overdue_invoices_count' => \App\Models\Invoice::whereDate('due_date', '<', now())
                ->where('status', '!=', 'draft')
                ->whereColumn('paid_amount', '<', 'total')
                ->count(),

            'total_outstanding' => \App\Models\Invoice::sum('total') - \App\Models\Invoice::sum('paid_amount'),

            'today_checkins' => Checkin::today()->count(),
            'pending_checkins' => Checkin::pending()->count(),
            'in_progress_checkins' => Checkin::inProgress()->count(),
            'completed_checkins' => Checkin::completed()->count(),

            // Growth metrics (calculated month-over-month)
            'customer_growth' => $customerGrowth,
            'revenue_growth' => $revenueGrowth,
        ];
    }

    /**
     * Get recent activities for the dashboard feed.
     */
    public function getRecentActivities(): \Illuminate\Support\Collection
    {
        $activities = [];

        // Recent customer registrations
        $recentCustomers = Customer::latest()->take(2)->get();
        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'type' => 'customer',
                'icon' => 'user',
                'title' => 'New customer registered',
                'description' => $customer->name,
                'time' => $customer->created_at->diffForHumans(),
                'color' => 'bg-[#1A53F2]',
            ];
        }

        // Recent check-ins
        $recentCheckins = Checkin::with(['customer', 'vehicle'])
            ->latest('checkin_time')
            ->take(3)
            ->get();

        foreach ($recentCheckins as $checkin) {
            $activities[] = [
                'type' => 'checkin',
                'icon' => 'check',
                'title' => 'Vehicle check-in completed',
                'description' => $checkin->vehicle->display_name ?? 'Unknown Vehicle',
                'time' => $checkin->checkin_time->diffForHumans(),
                'color' => 'bg-[#5274E3]',
            ];
        }

        // Recent tire storage
        $recentTires = Tire::with(['customer', 'vehicle'])
            ->latest()
            ->take(2)
            ->get();

        foreach ($recentTires as $tire) {
            $activities[] = [
                'type' => 'tire',
                'icon' => 'building',
                'title' => 'Tires stored in hotel',
                'description' => $tire->quantity.'x '.$tire->season.' tires - Customer: '.($tire->customer->name ?? 'Unknown'),
                'time' => $tire->created_at->diffForHumans(),
                'color' => 'bg-[#6A88E8]',
            ];
        }

        // Sort activities by most recent
        return collect($activities)->sortByDesc('time')->take(6)->values();
    }

    /**
     * Get scheduled work orders for the calendar view.
     */
    public function getCalendarWorkOrders($start, $end, string $bay = 'all', string $technician = 'all'): \Illuminate\Support\Collection
    {
        // Ensure Carbon instances
        $start = \Carbon\Carbon::parse($start)->startOfDay();
        $end = \Carbon\Carbon::parse($end)->endOfDay();

        $query = \App\Models\WorkOrder::query()
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('scheduled_at', [$start, $end])
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereNull('scheduled_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });

        if ($bay !== 'all') {
            $query->where('service_bay', $bay);
        }

        if ($technician !== 'all') {
            $query->where('technician_id', $technician);
        }

        return $query->with(['customer', 'vehicle', 'technician'])
            ->get()
            ->map(function ($wo) {
                $date = $wo->scheduled_at ?? $wo->created_at;

                return [
                    'id' => $wo->id,
                    'title' => ($wo->vehicle->make ?? '').' - '.($wo->customer->name ?? 'Guest'),
                    'start' => $date->format('Y-m-d H:i:s'),
                    'bay' => $wo->service_bay,
                    'technician' => $wo->technician->name ?? 'Unassigned',
                    'status' => $wo->status,
                    'color' => $wo->status_badge_color,
                ];
            });
    }

    /**
     * Get today's detailed schedule.
     */
    public function getTodaysSchedule(): \Illuminate\Support\Collection
    {
        return \App\Models\WorkOrder::query()
            ->where(function ($q) {
                $q->whereDate('scheduled_at', today())
                    ->orWhere(function ($q2) {
                        $q2->whereNull('scheduled_at')
                            ->whereDate('created_at', today());
                    });
            })
            ->with(['customer', 'vehicle', 'technician'])
            ->get()
            ->sortBy(function ($wo) {
                return $wo->scheduled_at ?? $wo->created_at;
            })
            ->map(function ($wo) {
                $time = $wo->scheduled_at ?? $wo->created_at;

                return [
                    'id' => $wo->id,
                    'time' => $time->format('H:i'),
                    'customer' => $wo->customer->name ?? 'Unknown',
                    'vehicle' => ($wo->vehicle->make ?? '').' '.($wo->vehicle->model ?? ''),
                    'technician' => $wo->technician->name ?? 'Unassigned',
                    'bay' => $wo->service_bay,
                    'status' => $wo->status,
                    'status_label' => $wo->status_label,
                    'status_color' => $wo->status_badge_color,
                ];
            });
    }

    /**
     * Get technician status board.
     */
    public function getTechnicianStatus(): \Illuminate\Support\Collection
    {
        // Get all technicians (users with technician role or all users for now)
        $technicians = \App\Models\User::all(); // Filter by role if applicable

        // Get currently active jobs
        $activeJobs = \App\Models\WorkOrder::where('status', 'in_progress')
            ->with(['technician', 'vehicle'])
            ->get()
            ->keyBy('technician_id');

        return $technicians->map(function ($tech) use ($activeJobs) {
            $currentJob = $activeJobs->get($tech->id);

            return [
                'id' => $tech->id,
                'name' => $tech->name,
                'status' => $currentJob ? 'busy' : 'available',
                'current_job' => $currentJob ? [
                    'id' => $currentJob->id,
                    'vehicle' => $currentJob->vehicle->display_name ?? 'Vehicle',
                    'bay' => $currentJob->service_bay,
                    'started_at' => $currentJob->started_at ? $currentJob->started_at->format('H:i') : null,
                    'duration' => $currentJob->started_at ? $currentJob->started_at->diffForHumans(null, true) : null,
                ] : null,
            ];
        });
    }

    /**
     * Get management alerts (Exceptions).
     */
    public function getAlerts(): \Illuminate\Support\Collection
    {
        $alerts = collect();

        // 1. Overdue Scheduled Jobs (Scheduled in past, not started)
        $overdueScheduled = \App\Models\WorkOrder::where('status', 'scheduled')
            ->where('scheduled_at', '<', now())
            ->count();

        if ($overdueScheduled > 0) {
            $alerts->push([
                'type' => 'warning',
                'message' => "$overdueScheduled jobs overdue to start",
                'action_url' => route('work-orders.index', ['status' => 'scheduled']),
            ]);
        }

        // 2. Long Running Jobs (> 4 hours)
        $longRunning = \App\Models\WorkOrder::where('status', 'in_progress')
            ->where('started_at', '<', now()->subHours(4))
            ->count();

        if ($longRunning > 0) {
            $alerts->push([
                'type' => 'info',
                'message' => "$longRunning jobs running > 4 hours",
                'action_url' => route('work-orders.index', ['status' => 'in_progress']),
            ]);
        }

        // 3. Low Stock (Placeholder - requires detailed inventory logic)
        // $lowStock = \App\Models\Part::whereColumn('quantity', '<', 'min_quantity')->count();
        // if ($lowStock > 0) { ... }

        return $alerts;
    }

    /**
     * Get recent check-ins with detailed info.
     */
    public function getRecentCheckins(): \Illuminate\Support\Collection
    {
        return Checkin::with(['customer', 'vehicle'])
            ->latest('checkin_time')
            ->take(5)
            ->get()
            ->map(function ($checkin) {
                return [
                    'id' => $checkin->id,
                    'customer_name' => $checkin->customer->name ?? 'Unknown Customer',
                    'vehicle_name' => $checkin->vehicle->display_name ?? 'Unknown Vehicle',
                    'service_type' => $checkin->service_type,
                    'status' => $checkin->status,
                    'status_color' => $checkin->status_badge_color,
                    'priority' => $checkin->priority,
                    'priority_color' => $checkin->priority_badge_color,
                    'service_bay' => $checkin->service_bay,
                    'checkin_time' => $checkin->checkin_time->format('M j, g:i A'),
                    'time_ago' => $checkin->checkin_time->diffForHumans(),
                    'duration' => $checkin->duration,
                ];
            });
    }

    /**
     * Get tire operations for the dashboard.
     */
    public function getTireOperations(): \Illuminate\Support\Collection
    {
        $operations = [];

        // Recently stored tires (last 7 days)
        $recentStorage = Tire::with(['customer', 'vehicle'])
            ->where('storage_date', '>=', now()->subDays(7))
            ->where('status', 'stored')
            ->latest('storage_date')
            ->take(3)
            ->get();

        foreach ($recentStorage as $tire) {
            $operations[] = [
                'type' => 'recent_storage',
                'icon' => 'archive',
                'title' => 'Recently stored',
                'description' => ($tire->customer->name ?? 'Unknown').' - '.$tire->full_description,
                'time_info' => $tire->storage_date->diffForHumans(),
                'location' => $tire->storage_location,
                'color' => 'bg-green-100 text-green-800',
                'action_url' => route('tires-hotel'),
            ];
        }

        // Tires ready for pickup
        $readyPickup = Tire::with(['customer', 'vehicle'])
            ->where('status', 'ready_pickup')
            ->latest('updated_at')
            ->take(3)
            ->get();

        foreach ($readyPickup as $tire) {
            $operations[] = [
                'type' => 'ready_pickup',
                'icon' => 'truck',
                'title' => 'Ready for pickup',
                'description' => ($tire->customer->name ?? 'Unknown').' - '.$tire->full_description,
                'time_info' => 'Updated '.$tire->updated_at->diffForHumans(),
                'location' => $tire->storage_location,
                'color' => 'bg-blue-100 text-blue-800',
                'action_url' => route('tires-hotel'),
            ];
        }

        // Active tire maintenance
        $maintenance = Tire::with(['customer', 'vehicle'])
            ->where('status', 'maintenance')
            ->latest('updated_at')
            ->take(2)
            ->get();

        foreach ($maintenance as $tire) {
            $operations[] = [
                'type' => 'maintenance',
                'icon' => 'cog',
                'title' => 'In maintenance',
                'description' => ($tire->customer->name ?? 'Unknown').' - '.$tire->full_description,
                'time_info' => 'Started '.$tire->updated_at->diffForHumans(),
                'location' => $tire->storage_location,
                'color' => 'bg-yellow-100 text-yellow-800',
                'action_url' => route('tires-hotel'),
            ];
        }

        // If no operations, show some recent tire activities
        if (empty($operations)) {
            $recentAny = Tire::with(['customer', 'vehicle'])
                ->latest('updated_at')
                ->take(3)
                ->get();

            foreach ($recentAny as $tire) {
                $operations[] = [
                    'type' => 'recent_activity',
                    'icon' => 'refresh',
                    'title' => 'Recent activity',
                    'description' => ($tire->customer->name ?? 'Unknown').' - '.$tire->full_description,
                    'time_info' => $tire->updated_at->diffForHumans(),
                    'location' => $tire->storage_location,
                    'color' => 'bg-gray-100 text-gray-800',
                    'action_url' => route('tires-hotel'),
                ];
            }
        }

        return collect($operations)->sortByDesc('time_info')->take(5)->values();
    }

    /**
     * Get service bay status.
     */
    public function getServiceBayStatus(): array
    {
        $bayNames = config('crm.service_bays.names');
        $bays = array_fill_keys($bayNames, 'available');

        // Get current check-ins by service bay
        $activeCheckins = Checkin::with(['customer', 'vehicle'])
            ->active()
            ->get()
            ->groupBy('service_bay');

        foreach ($activeCheckins as $bayName => $checkins) {
            if (isset($bays[$bayName])) {
                $checkin = $checkins->first();
                $bays[$bayName] = [
                    'status' => 'occupied',
                    'customer' => $checkin->customer->name ?? 'Unknown Customer',
                    'vehicle' => $checkin->vehicle->display_name ?? 'Unknown Vehicle',
                    'service_type' => $checkin->service_type,
                    'priority' => $checkin->priority,
                    'priority_color' => $checkin->priority_badge_color,
                    'duration' => $checkin->duration,
                    'checkin_id' => $checkin->id,
                ];
            }
        }

        return $bays;
    }

    /**
     * Get system status (simplified - in production, implement real checks).
     */
    public function getSystemStatus(): array
    {
        // TODO: Implement real system health checks
        return [
            'database' => [
                'status' => 'online',
                'uptime' => '99.9%',
                'color' => 'green',
            ],
            'storage' => [
                'status' => '78% used',
                'details' => '156 GB free',
                'color' => 'green',
            ],
            'cache' => [
                'status' => 'optimized',
                'hit_rate' => '94%',
                'color' => 'green',
            ],
            'backup' => [
                'status' => 'updated',
                'last_backup' => '2 hours ago',
                'color' => 'green',
            ],
        ];
    }
}
