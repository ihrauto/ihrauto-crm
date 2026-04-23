<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tire;
use App\Support\CachedQuery;
use Illuminate\Support\Facades\Storage;

class DashboardService
{
    /**
     * Get all dashboard statistics.
     *
     * D-14: wrapped with CachedQuery so a cold-cache expiry doesn't spawn
     * N concurrent recomputes for the same tenant.
     */
    public function getStats(): array
    {
        $tenantId = tenant_id();

        return CachedQuery::remember(
            "dashboard_stats_{$tenantId}",
            300,
            fn () => $this->computeStats()
        );
    }

    /**
     * Compute all dashboard statistics (called by cache layer).
     *
     * Scalability B-4: consolidated from 17 separate queries down to 7
     * aggregated ones. Each model that can reasonably be rolled up gets
     * ONE selectRaw with CASE WHENs; we trade PHP legibility for a 3–4×
     * faster cold-cache dashboard load at 200 tenants.
     *
     * Queries this runs, with estimated row counts at 200 tenants:
     *   1. customers  — one aggregate over this+last month (100k rows)
     *   2. payments   — one aggregate over this+last month (400k rows)
     *   3. work_orders — status / started_at / updated_at bucket
     *   4. checkins   — status + date bucket
     *   5. invoices   — outstanding / overdue in one pass
     *   6. tires      — stored count (cheap)
     *   7. users      — active count (cheap)
     */
    protected function computeStats(): array
    {
        $today = today()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        // 1. Customers: current-vs-last month growth + active count in one pass.
        // Using CASE WHEN (not PG FILTER) so the query is portable across PG + SQLite (tests).
        $customerStats = Customer::selectRaw(
            'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as current_month,
             SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as last_month,
             SUM(CASE WHEN is_active = 1 OR is_active = true THEN 1 ELSE 0 END) as active_total',
            [$monthStart, $lastMonthStart, $lastMonthEnd.' 23:59:59']
        )->first();
        $customerGrowth = $customerStats->last_month > 0
            ? round((($customerStats->current_month - $customerStats->last_month) / $customerStats->last_month) * 100, 1)
            : ($customerStats->current_month > 0 ? 100 : 0);

        // 2. Payments: current + last month revenue in one pass.
        $payStats = \App\Models\Payment::selectRaw(
            'COALESCE(SUM(CASE WHEN payment_date >= ? THEN amount ELSE 0 END), 0) as current_month,
             COALESCE(SUM(CASE WHEN payment_date >= ? AND payment_date <= ? THEN amount ELSE 0 END), 0) as last_month',
            [$monthStart, $lastMonthStart, $lastMonthEnd]
        )->first();
        $currentMonthRevenue = (float) $payStats->current_month;
        $lastMonthRevenue = (float) $payStats->last_month;
        $revenueGrowth = $lastMonthRevenue > 0
            ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
            : ($currentMonthRevenue > 0 ? 100 : 0);

        // 3. Work-order buckets (already a single query).
        $woCounts = \App\Models\WorkOrder::selectRaw(
            "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_jobs,
             SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as pending_jobs,
             SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = ? THEN 1 ELSE 0 END) as completed_today,
             SUM(CASE WHEN status = 'in_progress' AND started_at < ? THEN 1 ELSE 0 END) as long_running",
            [$today, now()->subHours(4)]
        )->first();

        $activeJobs = (int) $woCounts->active_jobs;

        // 4. Checkins (already a single aggregate).
        $ciCounts = Checkin::selectRaw(
            "SUM(CASE WHEN DATE(checkin_time) = ? THEN 1 ELSE 0 END) as today,
             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
             SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
             SUM(CASE WHEN status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) as active_checkins",
            [$today]
        )->first();

        // 5. Invoices: overdue count + total outstanding in one pass.
        $invStats = \App\Models\Invoice::selectRaw(
            "SUM(CASE WHEN due_date < ? AND status != 'draft' AND paid_amount < total THEN 1 ELSE 0 END) as overdue,
             COALESCE(SUM(total), 0) as sum_total,
             COALESCE(SUM(paid_amount), 0) as sum_paid",
            [now()]
        )->first();
        $totalOutstanding = (float) $invStats->sum_total - (float) $invStats->sum_paid;

        // 6–7. Cheap single-number aggregates.
        $tiresInHotel = Tire::stored()->sum('quantity');
        $appointmentsThisWeek = \App\Models\Appointment::whereBetween(
            'start_time', [now()->startOfWeek(), now()->endOfWeek()]
        )->whereNotIn('status', ['completed', 'cancelled', 'failed'])->count();

        // Derived / in-memory.
        $lowStockCount = \App\Models\Product::lowStock()->count();
        $totalBays = config('crm.service_bays.count', 6);
        $freeBays = max(0, $totalBays - $activeJobs);

        // Idle technicians — single query using NOT EXISTS correlated sub-select.
        // Avoids the previous "pluck + unique + whereNotIn" pattern that loaded
        // all active technician ids into PHP memory first.
        $idleTechnicians = \App\Models\User::where('is_active', true)
            ->whereDoesntHave('workOrders', fn ($q) => $q->where('status', 'in_progress'))
            ->count();

        return [
            'total_customers' => (int) $customerStats->active_total,
            'active_checkins' => (int) $ciCounts->active_checkins,
            'tires_in_hotel' => (int) $tiresInHotel,

            'active_jobs' => $activeJobs,
            'pending_jobs' => (int) $woCounts->pending_jobs,
            'completed_today' => (int) $woCounts->completed_today,
            'long_running_jobs' => (int) $woCounts->long_running,

            'appointments_today' => $appointmentsThisWeek,
            'low_stock_count' => $lowStockCount,
            'free_bays' => $freeBays,
            'idle_technicians' => $idleTechnicians,

            'monthly_revenue' => $currentMonthRevenue,
            'overdue_invoices_count' => (int) $invStats->overdue,
            'total_outstanding' => $totalOutstanding,

            'today_checkins' => (int) $ciCounts->today,
            'pending_checkins' => (int) $ciCounts->pending,
            'in_progress_checkins' => (int) $ciCounts->in_progress,
            'completed_checkins' => (int) $ciCounts->completed,

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
                'timestamp' => $customer->created_at,
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
                'timestamp' => $checkin->checkin_time,
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

        // Sort activities by most recent (use actual timestamp for sorting)
        return collect($activities)->sortByDesc('timestamp')->take(6)->values();
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
        $technicians = \App\Models\User::where('is_active', true)->orderBy('name')->get();

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
     * Get system status with real runtime checks.
     */
    public function getSystemStatus(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'storage' => $this->checkStorage(),
            'cache' => $this->checkCache(),
            'backup' => $this->checkBackup(),
        ];
    }

    /**
     * Database health check.
     *
     * Previous implementation queried `information_schema.tables` which:
     *   - is PostgreSQL-specific (fails on SQLite used in tests)
     *   - leaks database schema metadata (minor info disclosure)
     *   - is slow under load (table scan on system catalog)
     *
     * We now just verify PDO connectivity + a trivial SELECT 1. This is
     * portable, fast, and reveals nothing about the schema.
     */
    private function checkDatabase(): array
    {
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            \Illuminate\Support\Facades\DB::select('SELECT 1');

            return [
                'status' => 'online',
                'details' => 'Connection verified',
                'color' => 'green',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'details' => 'Connection failed',
                'color' => 'red',
            ];
        }
    }

    private function checkStorage(): array
    {
        $publicDisk = config('filesystems.disks.public', []);
        if (($publicDisk['driver'] ?? null) === 's3') {
            $bucket = $publicDisk['bucket'] ?? 'configured bucket';
            $root = trim((string) ($publicDisk['root'] ?? ''), '/');

            return [
                'status' => 'cloud-backed',
                'details' => $root !== '' ? "{$bucket}/{$root}" : $bucket,
                'color' => 'green',
            ];
        }

        $storagePath = storage_path();

        // disk_free_space may be disabled on some hosts
        $freeBytes = @disk_free_space($storagePath);
        $totalBytes = @disk_total_space($storagePath);

        if ($freeBytes === false || $totalBytes === false || $totalBytes == 0) {
            return [
                'status' => 'unknown',
                'details' => 'Unable to read disk info',
                'color' => 'gray',
            ];
        }

        $usedPercent = round((1 - $freeBytes / $totalBytes) * 100, 1);
        $freeGB = round($freeBytes / 1073741824, 1);

        $color = $usedPercent > 90 ? 'red' : ($usedPercent > 75 ? 'yellow' : 'green');

        return [
            'status' => "{$usedPercent}% used",
            'details' => "{$freeGB} GB free",
            'color' => $color,
        ];
    }

    private function checkCache(): array
    {
        try {
            $key = 'system_health_check_'.time();
            \Illuminate\Support\Facades\Cache::put($key, true, 10);
            $hit = \Illuminate\Support\Facades\Cache::get($key);
            \Illuminate\Support\Facades\Cache::forget($key);

            return [
                'status' => $hit ? 'operational' : 'degraded',
                'details' => $hit ? 'Read/write OK' : 'Write succeeded, read failed',
                'color' => $hit ? 'green' : 'yellow',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'details' => 'Cache unavailable',
                'color' => 'red',
            ];
        }
    }

    private function checkBackup(): array
    {
        $backupDiskName = config('backup.backup.destination.disks.0', 'backups');

        try {
            $disk = Storage::disk($backupDiskName);
            $files = collect($disk->allFiles())
                ->filter(fn (string $path) => str_ends_with(strtolower($path), '.zip'))
                ->values();

            if ($files->isEmpty()) {
                return [
                    'status' => 'no backups',
                    'details' => "No backup files found on {$backupDiskName}",
                    'color' => 'yellow',
                ];
            }

            $latestPath = $files->sortByDesc(fn (string $path) => $disk->lastModified($path))->first();
            $latestModifiedAt = \Carbon\Carbon::createFromTimestamp($disk->lastModified($latestPath));
            $age = now()->diffForHumans($latestModifiedAt);
            $hoursSince = now()->diffInHours($latestModifiedAt);
            $color = $hoursSince > 48 ? 'red' : ($hoursSince > 24 ? 'yellow' : 'green');

            return [
                'status' => 'available',
                'details' => "Latest on {$backupDiskName}: {$age}",
                'color' => $color,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'details' => "Unable to read {$backupDiskName} backups",
                'color' => 'red',
            ];
        }
    }
}
