<?php

namespace App\Support;

/**
 * ENG-009: Single source of truth for dashboard widgets.
 *
 * Each widget declares its identity, gating, defaults, and how it renders.
 * The Dashboard Studio panel reads from here. The dashboard view loops
 * over this list filtered by what the user enabled + what the tenant's
 * plan allows.
 *
 * Widget shape:
 *   - key:                stable string ID, never renamed (used in DB)
 *   - label:              user-facing name shown in the Studio panel
 *   - description:        one-line hint shown under the label
 *   - category:           grouping in the Studio panel (see categories())
 *   - module:             module key for tenant plan gating ("work-orders",
 *                         "finance", etc.). null = available to every plan
 *   - permission:         optional Spatie permission gate. null = all roles
 *   - default_for_roles:  which roles see this widget ON by default
 *   - partial:            Blade partial path under resources/views/
 *   - size:               'small' (fits in stat-grid) or 'full' (full row)
 *   - data_provider:      method on DashboardService to fetch this widget's
 *                         data; null = no data needed (purely static markup)
 *
 * Adding a widget = 1 entry here + 1 partial. Phase 3 features (TÜV
 * reminders, dunning summary, Stripe events, etc.) will land as additional
 * entries without touching the controller, the studio service, or routes.
 */
class DashboardWidgetCatalog
{
    public const VERSION = 1;

    /**
     * Maximum number of keys a single user may persist. Defense against
     * JSON-bloat / accidental clients pushing huge payloads. The catalog
     * itself is well under this; the cap matters for write-side validation.
     */
    public const MAX_KEYS = 50;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ---------- KPI hero row (dashboard redesign 2026-04-26) ----------
            // Modern small-card KPIs with sparklines. Each one replaces an
            // older single-number widget; the legacy versions are kept in
            // the catalog (further down) but disabled by default so power
            // users can re-enable them via the Studio panel.
            'kpi_revenue' => [
                'label' => 'Revenue (KPI)',
                'description' => 'Revenue this month + 30-day sparkline.',
                'category' => 'kpi',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.kpi-revenue',
                'size' => 'small',
                'data_provider' => 'getRevenueSparkline',
            ],
            'kpi_active_jobs' => [
                'label' => 'Active Jobs (KPI)',
                'description' => 'In-progress count + bay capacity context.',
                'category' => 'kpi',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'technician'],
                'partial' => 'dashboard.widgets.kpi-active-jobs',
                'size' => 'small',
                'data_provider' => 'getActiveJobsSparkline',
            ],
            'kpi_outstanding' => [
                'label' => 'Outstanding (KPI)',
                'description' => 'Unpaid invoice total + 30-day trend.',
                'category' => 'kpi',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.kpi-outstanding',
                'size' => 'small',
                'data_provider' => 'getOutstandingSparkline',
            ],
            'kpi_new_customers' => [
                'label' => 'New Customers (KPI)',
                'description' => 'Last 30 days of new customer registrations.',
                'category' => 'kpi',
                'module' => 'customers',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.kpi-new-customers',
                'size' => 'small',
                'data_provider' => 'getNewCustomersSparkline',
            ],

            // ---------- Operations ----------
            'active_jobs' => [
                'label' => 'Active Jobs',
                'description' => 'Work orders currently in progress.',
                'category' => 'operations',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'technician'],
                'partial' => 'dashboard.widgets.active-jobs',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'pending_jobs' => [
                'label' => 'Pending Jobs',
                'description' => 'Work orders waiting to start.',
                'category' => 'operations',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'technician'],
                'partial' => 'dashboard.widgets.pending-jobs',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'completed_today' => [
                'label' => 'Completed Today',
                'description' => 'Jobs finished and ready to invoice.',
                'category' => 'operations',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.completed-today',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'all_work_orders' => [
                'label' => 'All Work Orders',
                'description' => 'Lifetime total.',
                'category' => 'operations',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.all-work-orders',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'todays_schedule' => [
                'label' => "Today's Schedule",
                'description' => 'Jobs scheduled for today, with bay and tech.',
                'category' => 'operations',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'technician', 'receptionist'],
                'partial' => 'dashboard.widgets.todays-schedule',
                'size' => 'full',
                'widget_type' => 'timeline',
                'data_provider' => 'getTodaysSchedule',
            ],
            'technician_status' => [
                'label' => 'Technician Status',
                'description' => 'Who is busy, who is free, and on what.',
                'category' => 'operations',
                'module' => 'management',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.technician-status',
                'size' => 'full',
                'widget_type' => 'pinboard',
                'data_provider' => 'getTechnicianStatus',
            ],
            'idle_technicians' => [
                'label' => 'Idle Staff',
                'description' => 'Technicians available right now.',
                'category' => 'operations',
                'module' => 'management',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.idle-technicians',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'free_bays' => [
                'label' => 'Free Bays',
                'description' => 'Service bays not currently occupied.',
                'category' => 'operations',
                'module' => 'management',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.free-bays',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'long_running_jobs' => [
                'label' => 'Long-Running Jobs',
                'description' => 'Jobs in progress for more than 4 hours.',
                'category' => 'operations',
                'module' => 'work-orders',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.long-running-jobs',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],

            // ---------- Customer ----------
            'appointments_today' => [
                'label' => 'Appointments Today',
                'description' => 'Bookings scheduled for today.',
                'category' => 'customer',
                'module' => 'management',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.appointments-today',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'total_customers' => [
                'label' => 'Total Customers',
                'description' => 'Active customer accounts with growth trend.',
                'category' => 'customer',
                'module' => 'customers',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.total-customers',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'today_checkins' => [
                'label' => "Today's Check-ins",
                'description' => 'Vehicles checked in today.',
                'category' => 'customer',
                'module' => 'check-in',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.today-checkins',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'pending_checkins' => [
                'label' => 'Pending Check-ins',
                'description' => 'Check-ins awaiting service.',
                'category' => 'customer',
                'module' => 'check-in',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.pending-checkins',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'recent_customers' => [
                'label' => 'Recent Customers',
                'description' => 'Most recently added customer accounts.',
                'category' => 'customer',
                'module' => 'customers',
                'permission' => null,
                'default_for_roles' => [],
                'partial' => 'dashboard.widgets.recent-customers',
                'size' => 'half',
                'widget_type' => 'list',
                'data_provider' => 'getRecentCustomers',
            ],

            // ---------- Finance ----------
            'monthly_revenue' => [
                'label' => 'Monthly Revenue (legacy)',
                'description' => 'Total payments received this month. Replaced by Revenue (KPI) — opt-in via the Studio panel if you prefer the legacy single-number tile.',
                'category' => 'finance',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => [], // dashboard redesign 2026-04-26 — superseded by kpi_revenue
                'partial' => 'dashboard.widgets.monthly-revenue',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'outstanding_balance' => [
                'label' => 'Outstanding Balance (legacy)',
                'description' => 'Replaced by Outstanding (KPI) with sparkline trend. Available as opt-in.',
                'category' => 'finance',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => [], // dashboard redesign 2026-04-26 — superseded by kpi_outstanding
                'partial' => 'dashboard.widgets.outstanding-balance',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'overdue_invoices' => [
                'label' => 'Overdue Invoices',
                'description' => 'Invoices past their due date.',
                'category' => 'finance',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.overdue-invoices',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            // ENG-012: inspection reminders driver. The widget surfaces
            // vehicles whose TÜV / MFK / §57a is due in the next 30 days
            // so an operator can phone customers who don't reply to the
            // automated SMS.
            'inspections_due' => [
                'label' => 'Inspections Due',
                'description' => 'TÜV / MFK / §57a coming up in 30 days.',
                'category' => 'customer',
                'module' => 'customers',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.inspections-due',
                'size' => 'half',
                'widget_type' => 'list',
                'data_provider' => 'getInspectionsDue',
            ],
            'recent_payments' => [
                'label' => 'Recent Payments',
                'description' => 'Latest customer payments received.',
                'category' => 'finance',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => [],
                'partial' => 'dashboard.widgets.recent-payments',
                'size' => 'half',
                'widget_type' => 'list',
                'data_provider' => 'getRecentPayments',
            ],
            'recent_invoices' => [
                'label' => 'Recent Invoices',
                'description' => 'Latest issued invoices with status.',
                'category' => 'finance',
                'module' => 'finance',
                'permission' => null,
                'default_for_roles' => [],
                'partial' => 'dashboard.widgets.recent-invoices',
                'size' => 'half',
                'widget_type' => 'list',
                'data_provider' => 'getRecentInvoices',
            ],

            // ---------- Inventory ----------
            'low_stock' => [
                'label' => 'Low Stock',
                'description' => 'Parts at or below their minimum level.',
                'category' => 'inventory',
                'module' => 'management',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.low-stock',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],
            'tires_in_hotel' => [
                'label' => 'Tires in Storage',
                'description' => 'Total tire sets currently in the hotel.',
                'category' => 'inventory',
                'module' => 'tire-hotel',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager'],
                'partial' => 'dashboard.widgets.tires-in-hotel',
                'size' => 'small',
                'data_provider' => 'getStats',
            ],

            // ---------- Quick Actions ----------
            'quick_action_checkin' => [
                'label' => 'Quick Action: New Check-in',
                'description' => 'Shortcut to register a vehicle arrival.',
                'category' => 'shortcuts',
                'module' => 'check-in',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.quick-action-checkin',
                'size' => 'half',
                'data_provider' => null,
            ],
            'quick_action_tire_storage' => [
                'label' => 'Quick Action: New Tire Storage',
                'description' => 'Shortcut to store tires for a customer.',
                'category' => 'shortcuts',
                'module' => 'tire-hotel',
                'permission' => null,
                'default_for_roles' => ['admin', 'manager', 'receptionist'],
                'partial' => 'dashboard.widgets.quick-action-tire-storage',
                'size' => 'half',
                'data_provider' => null,
            ],
        ];
    }

    /**
     * @return array<string, string> key => display label
     */
    public static function categories(): array
    {
        return [
            'kpi' => 'Headline KPIs',
            'operations' => 'Operations',
            'customer' => 'Customer',
            'finance' => 'Finance',
            'inventory' => 'Inventory',
            'shortcuts' => 'Quick Actions',
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Default widget keys for a user with the given roles. Union across
     * roles so multi-role users get a sensible aggregate (de-duped).
     *
     * @param  array<int, string>  $roles
     * @return array<int, string>
     */
    public static function defaultsForRoles(array $roles): array
    {
        $defaults = [];
        foreach (self::all() as $key => $widget) {
            foreach ($roles as $role) {
                if (in_array($role, $widget['default_for_roles'], true)) {
                    $defaults[] = $key;
                    break;
                }
            }
        }

        return array_values(array_unique($defaults));
    }
}
