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
     *
     * Bug review UX-03: assert we have a tenant context before running
     * any aggregation. `TenantScope` silently skips the tenant filter
     * when `tenant_id()` returns null (see app/Scopes/TenantScope.php:29),
     * which means a scheduled job or CLI command that forgets to set
     * the tenant would return aggregated numbers across ALL tenants.
     * We'd rather fail loudly than silently leak.
     */
    public function getStats(): array
    {
        $tenantId = tenant_id();

        if ($tenantId === null) {
            throw new \LogicException(
                'DashboardService::getStats() requires a resolved tenant context. '
                .'TenantScope silently returns data across all tenants when no tenant '
                .'is bound — fail loudly here rather than leak cross-tenant aggregates.'
            );
        }

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
        //
        // Boolean comparison portability: `is_active = 1 OR is_active = true` was
        // meant to be driver-agnostic but PostgreSQL rejects `boolean = integer`
        // at parse time — the whole expression fails before the OR is evaluated.
        // Using the boolean column directly in the WHEN condition works on every
        // supported driver (PG treats bool natively; SQLite/MySQL treat any
        // non-zero value as truthy in a boolean context).
        $customerStats = Customer::selectRaw(
            'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as current_month,
             SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as last_month,
             SUM(CASE WHEN is_active THEN 1 ELSE 0 END) as active_total',
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

        // 3. Work-order buckets (already a single query). Includes the
        // lifetime total so the all-work-orders widget doesn't have to
        // run its own query inside the Blade view (audit F-15).
        $woCounts = \App\Models\WorkOrder::selectRaw(
            "SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_jobs,
             SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as pending_jobs,
             SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = ? THEN 1 ELSE 0 END) as completed_today,
             SUM(CASE WHEN status = 'in_progress' AND started_at < ? THEN 1 ELSE 0 END) as long_running,
             COUNT(*) as total",
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
            'all_work_orders' => (int) $woCounts->total,

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
     * ENG-012: vehicles with TÜV / MFK / §57a due in the next 30 days.
     * Backs the "Inspections Due" dashboard widget. Tenant-scoped via
     * Vehicle's BelongsToTenant trait — this method asserts a tenant
     * context (same fail-loud rule as getStats).
     */
    public function getInspectionsDue(): \Illuminate\Support\Collection
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            throw new \LogicException(
                'DashboardService::getInspectionsDue() requires a resolved tenant context.'
            );
        }

        $today = today();

        return \App\Models\Vehicle::query()
            ->whereNotNull('next_inspection_at')
            ->whereBetween('next_inspection_at', [
                $today->toDateString(),
                $today->copy()->addDays(30)->toDateString(),
            ])
            ->with('customer:id,name,phone')
            ->orderBy('next_inspection_at')
            ->limit(10)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'plate' => $v->license_plate,
                'make_model' => trim(($v->make ?? '').' '.($v->model ?? '')),
                'customer_name' => $v->customer?->name,
                'authority' => $v->inspectionAuthorityLabel(),
                'due_date' => $v->next_inspection_at->format('d.m.Y'),
                'days_out' => (int) $today->diffInDays($v->next_inspection_at, absolute: false),
            ]);
    }

    /**
     * Recent customer payments for the dashboard list widget.
     */
    public function getRecentPayments(): \Illuminate\Support\Collection
    {
        return \App\Models\Payment::with(['invoice.customer'])
            ->latest('payment_date')
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'customer' => $p->invoice?->customer?->name ?? 'Unknown',
                'invoice_id' => $p->invoice_id,
                'date' => $p->payment_date?->format('M j'),
            ]);
    }

    /**
     * Recent issued invoices for the dashboard list widget.
     */
    public function getRecentInvoices(): \Illuminate\Support\Collection
    {
        return \App\Models\Invoice::with('customer')
            ->where('status', '!=', 'draft')
            ->latest('issued_at')
            ->take(5)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'number' => $inv->invoice_number ?? '#'.$inv->id,
                'customer' => $inv->customer?->name ?? 'Unknown',
                'total' => (float) $inv->total,
                'balance' => (float) $inv->total - (float) $inv->paid_amount,
                'status' => $inv->status,
                'issued_at' => $inv->issued_at?->format('M j'),
            ]);
    }

    /**
     * Recent customers for the dashboard list widget.
     */
    public function getRecentCustomers(): \Illuminate\Support\Collection
    {
        return Customer::latest()
            ->take(5)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'phone' => $c->phone,
                'created_at' => $c->created_at,
            ]);
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

    // =================================================================
    // Dashboard redesign 2026-04-26 — sparkline + analytics methods
    //
    // Each `getXxxSparkline()` returns the last $days values as a flat
    // numeric array, ready to feed an ApexCharts sparkline. We do NOT
    // return labels — sparklines are deliberately label-less, only the
    // shape matters at this size. Tenant scoping is implicit via the
    // model's BelongsToTenant trait; we still assert tenant context to
    // fail closed (UX-03 pattern).
    //
    // Heavy aggregations are wrapped in CachedQuery::remember with a
    // 5-minute TTL — sparklines are not real-time and the dashboard is
    // viewed many times per request session.
    // =================================================================

    /**
     * Daily revenue (sum of payments) for the last $days days.
     * Powers the Revenue KPI sparkline.
     */
    public function getRevenueSparkline(int $days = 30): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_spark_rev_{$tenantId}_{$days}",
            300,
            fn () => $this->dailySeries(
                model: \App\Models\Payment::class,
                column: 'payment_date',
                aggregate: 'sum',
                aggregateColumn: 'amount',
                days: $days,
            ),
        );
    }

    /**
     * Daily count of in-progress work orders over the last $days days.
     * Powers the Active Jobs KPI sparkline.
     */
    public function getActiveJobsSparkline(int $days = 30): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_spark_aj_{$tenantId}_{$days}",
            300,
            fn () => $this->dailySeries(
                model: \App\Models\WorkOrder::class,
                column: 'created_at',
                aggregate: 'count',
                days: $days,
            ),
        );
    }

    /**
     * Daily outstanding-balance trend (sum of total-paid_amount on
     * non-paid, non-void invoices created up to that day). Powers
     * the Outstanding Balance KPI sparkline.
     *
     * Note: this is "snapshot at end of each day" semantics — it
     * approximates the running outstanding balance by re-computing the
     * end-of-day total each day. Acceptable at sparkline resolution.
     */
    public function getOutstandingSparkline(int $days = 30): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_spark_outst_{$tenantId}_{$days}",
            300,
            function () use ($days) {
                // Approx: per-day net = SUM(total - paid_amount) of
                // invoices ISSUED on that day. That's "new debt created
                // today" — close enough to a balance trend for a
                // sparkline that's only meant to show direction.
                return $this->dailySeries(
                    model: \App\Models\Invoice::class,
                    column: 'issue_date',
                    aggregate: 'sum',
                    aggregateColumn: \DB::raw('total - paid_amount'),
                    days: $days,
                    additionalWhere: function ($q) {
                        $q->where('status', '!=', 'void')
                            ->where('status', '!=', 'draft');
                    },
                );
            },
        );
    }

    /**
     * Daily new-customer count over the last $days days.
     * Powers the New Customers KPI sparkline.
     */
    public function getNewCustomersSparkline(int $days = 30): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_spark_nc_{$tenantId}_{$days}",
            300,
            fn () => $this->dailySeries(
                model: Customer::class,
                column: 'created_at',
                aggregate: 'count',
                days: $days,
            ),
        );
    }

    /**
     * Customer mix segmentation:
     *   active     — at least one work order in last 90 days
     *   recent     — registered in last 30 days, no work order yet
     *   reengaged  — returned with a WO after a 6+ month gap
     *
     * Sum is NOT necessarily the total customer count — segments
     * are best-effort buckets, "other" is implicit.
     *
     * Powers the Customer Mix donut chart.
     */
    public function getCustomerMix(): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_customer_mix_{$tenantId}",
            300,
            function () {
                $now = now();

                // Customer has no direct `workOrders` relation — use exists
                // sub-queries against work_orders.customer_id directly.
                $activeCount = Customer::whereExists(function ($q) use ($now) {
                    $q->select(\DB::raw(1))
                        ->from('work_orders')
                        ->whereColumn('work_orders.customer_id', 'customers.id')
                        ->where('work_orders.created_at', '>=', $now->copy()->subDays(90));
                })->count();

                $recentCount = Customer::where('created_at', '>=', $now->copy()->subDays(30))
                    ->whereNotExists(function ($q) {
                        $q->select(\DB::raw(1))
                            ->from('work_orders')
                            ->whereColumn('work_orders.customer_id', 'customers.id');
                    })
                    ->count();

                // Re-engaged: any WO in last 30 days where the previous
                // WO for that customer was 6+ months prior. Single SQL.
                $reengagedCount = \App\Models\WorkOrder::query()
                    ->whereIn('customer_id', function ($sub) use ($now) {
                        $sub->select('customer_id')
                            ->from('work_orders as recent_wo')
                            ->where('recent_wo.created_at', '>=', $now->copy()->subDays(30))
                            ->whereExists(function ($prev) use ($now) {
                                $prev->select(\DB::raw(1))
                                    ->from('work_orders as prev_wo')
                                    ->whereColumn('prev_wo.customer_id', 'recent_wo.customer_id')
                                    ->where('prev_wo.created_at', '<', $now->copy()->subMonths(6))
                                    ->whereRaw('prev_wo.id != recent_wo.id');
                            });
                    })
                    ->distinct('customer_id')
                    ->count('customer_id');

                $totalCount = Customer::count();

                return [
                    'active' => $activeCount,
                    'recent' => $recentCount,
                    'reengaged' => $reengagedCount,
                    'total' => $totalCount,
                ];
            },
        );
    }

    /**
     * Service-bay utilisation heatmap: 7 days × 9 hours (08:00–17:00),
     * each cell = count of work orders that were "in progress" during
     * that hour. Approximation: a WO covers each hour between its
     * `started_at` and `completed_at` (or now() if still in progress).
     */
    public function getBayUtilization(): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_bay_util_{$tenantId}",
            300,
            function () {
                // Last 7 days × 9 working hours (08–17). Pre-fill grid
                // with zeros so missing data doesn't make the heatmap
                // look "broken".
                $grid = [];
                for ($d = 6; $d >= 0; $d--) {
                    $day = now()->subDays($d)->startOfDay();
                    $row = ['label' => $day->format('D'), 'date' => $day->toDateString(), 'cells' => []];
                    for ($h = 8; $h <= 16; $h++) {
                        $row['cells'][$h] = 0;
                    }
                    $grid[] = $row;
                }

                $weekStart = now()->subDays(6)->startOfDay();
                $weekEnd = now()->endOfDay();

                $orders = \App\Models\WorkOrder::whereNotNull('started_at')
                    ->where('started_at', '>=', $weekStart)
                    ->where('started_at', '<=', $weekEnd)
                    ->get(['started_at', 'completed_at']);

                foreach ($orders as $wo) {
                    $start = $wo->started_at;
                    $end = $wo->completed_at ?? now();

                    foreach ($grid as &$row) {
                        $rowDate = \Carbon\Carbon::parse($row['date']);
                        if (! $start->isSameDay($rowDate) && ! $end->isSameDay($rowDate)) {
                            // does not overlap with this row
                            continue;
                        }

                        // For each working hour, count if WO was in progress
                        for ($h = 8; $h <= 16; $h++) {
                            $hourStart = $rowDate->copy()->setTime($h, 0);
                            $hourEnd = $rowDate->copy()->setTime($h + 1, 0);
                            if ($start->lt($hourEnd) && $end->gt($hourStart)) {
                                $row['cells'][$h]++;
                            }
                        }
                    }
                    unset($row);
                }

                $totalBays = (int) config('crm.service_bays.count', 6);

                return [
                    'grid' => $grid,
                    'total_bays' => $totalBays,
                ];
            },
        );
    }

    /**
     * Top mechanics by completed work orders in the last $days days.
     * Powers the Top Mechanics bar chart.
     */
    public function getTopMechanics(int $days = 30, int $limit = 8): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_top_mech_{$tenantId}_{$days}_{$limit}",
            300,
            function () use ($days, $limit) {
                $since = now()->subDays($days);

                return \App\Models\WorkOrder::query()
                    ->where('status', 'completed')
                    ->where('completed_at', '>=', $since)
                    ->whereNotNull('technician_id')
                    ->selectRaw('technician_id, COUNT(*) as completed_count')
                    ->groupBy('technician_id')
                    ->orderByDesc('completed_count')
                    ->limit($limit)
                    ->with('technician:id,name')
                    ->get()
                    ->map(fn ($row) => [
                        'name' => $row->technician?->name ?? 'Unknown',
                        'count' => (int) $row->completed_count,
                    ])
                    ->all();
            },
        );
    }

    /**
     * Recent customer activity stream — alternating payments and
     * outstanding-balance changes. Used by the right-rail "Customer
     * Activity" widget.
     *
     * @return array<int, array{
     *     type: string, customer: string, amount: float,
     *     positive: bool, date: string, ref: string
     * }>
     */
    public function getCustomerActivityStream(int $limit = 6): array
    {
        $tenantId = $this->requireTenant(__METHOD__);

        return CachedQuery::remember(
            "dashboard_cust_activity_{$tenantId}_{$limit}",
            120,
            function () use ($limit) {
                $payments = \App\Models\Payment::with(['invoice.customer'])
                    ->latest('payment_date')
                    ->take($limit)
                    ->get()
                    ->map(fn ($p) => [
                        'type' => 'payment',
                        'customer' => $p->invoice?->customer?->name ?? 'Unknown',
                        'amount' => (float) $p->amount,
                        'positive' => true,
                        'date' => $p->payment_date?->format('M j'),
                        'ref' => $p->invoice?->invoice_number ?? ('#'.$p->invoice_id),
                    ]);

                return $payments->all();
            },
        );
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    /** Fail-closed tenant assertion (mirrors UX-03 pattern). */
    private function requireTenant(string $caller): int
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            throw new \LogicException(
                "{$caller} requires a resolved tenant context. "
                .'TenantScope silently returns cross-tenant data when no tenant '
                .'is bound — fail loudly here rather than leak.'
            );
        }

        return (int) $tenantId;
    }

    /**
     * Build a daily-binned aggregate series for the last $days days.
     * Pads missing days with zero so the sparkline doesn't have gaps.
     *
     * @param  string  $model  Eloquent model FQCN
     * @param  string  $column  date column to bucket on
     * @param  string  $aggregate  'count' | 'sum'
     * @param  string|\Illuminate\Database\Query\Expression|null  $aggregateColumn  required when aggregate=='sum'
     * @param  int  $days  how many days back
     * @param  callable|null  $additionalWhere  optional extra filter callback
     * @return array<int, float|int>
     */
    private function dailySeries(
        string $model,
        string $column,
        string $aggregate = 'count',
        $aggregateColumn = null,
        int $days = 30,
        ?callable $additionalWhere = null,
    ): array {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        // Driver-portable date bucket — strftime on SQLite, DATE() on PG.
        // DATE() works on both for date-only output.
        $bucket = "DATE({$column})";

        $query = $model::query()
            ->where($column, '>=', $start)
            ->where($column, '<=', $end);

        if ($additionalWhere !== null) {
            $additionalWhere($query);
        }

        if ($aggregate === 'sum') {
            if ($aggregateColumn === null) {
                throw new \InvalidArgumentException('sum aggregate requires aggregateColumn');
            }
            $rawAgg = $aggregateColumn instanceof \Illuminate\Database\Query\Expression
                ? "SUM({$aggregateColumn->getValue(\DB::connection()->getQueryGrammar())})"
                : "SUM({$aggregateColumn})";
            $query->selectRaw("{$bucket} as day, {$rawAgg} as v");
        } else {
            $query->selectRaw("{$bucket} as day, COUNT(*) as v");
        }

        $rows = $query->groupBy('day')->orderBy('day')->get()
            ->mapWithKeys(fn ($r) => [(string) $r->day => (float) $r->v])
            ->all();

        // Pad — make sure every day in [start..end] has a value.
        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();
            $series[] = $rows[$day] ?? 0;
        }

        return $series;
    }
}
