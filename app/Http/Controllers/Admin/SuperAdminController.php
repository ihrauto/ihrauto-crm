<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddBonusDaysRequest;
use App\Http\Requests\Admin\ArchiveTenantRequest;
use App\Http\Requests\Admin\TenantNoteRequest;
use App\Http\Requests\Admin\TenantReasonRequest;
use App\Http\Requests\Admin\UpdateTenantBillingRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\TenantLifecycleService;
use App\Support\TenantCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * S-11 — SUPER-ADMIN SCOPE BYPASS INVARIANTS.
 *
 * Routes in this controller run with `role:super-admin` middleware AND a
 * TenantMiddleware that deliberately bypasses tenant context for users
 * whose only role is `super-admin`. That means:
 *
 *  1. Eloquent queries here DO NOT have `TenantScope` applied — a raw
 *     `Tenant::find($id)` is free to cross tenant boundaries. This is
 *     intentional; the whole point of the super-admin surface is to
 *     administer all tenants.
 *
 *  2. Because the tenant scope is off, every write MUST explicitly set
 *     the correct `tenant_id` (do not rely on the `BelongsToTenant`
 *     creating hook, which only fires when `tenant_id()` resolves).
 *
 *  3. Every action that mutates a tenant or its data MUST emit an
 *     AuditLog entry. If a super-admin action is not audited, a
 *     compromised super-admin account is undetectable.
 *
 *  4. NEVER bind a tenant via `TenantContext::set()` inside super-admin
 *     flows — TenantContext::set() now throws on inactive tenants, and
 *     super-admin routinely operates on suspended / expired ones.
 *
 *  5. If you add a new endpoint here, add a test in
 *     tests/Feature/ManagementAdminTest.php that asserts the audit
 *     trail and tenant-targeting behaviour.
 */
class SuperAdminController extends Controller
{
    /**
     * Display a listing of all tenants.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $status = (string) $request->string('status', 'all');
        $plan = (string) $request->string('plan', 'all');
        $sort = (string) $request->string('sort', 'recent');

        $query = Tenant::query()
            ->withCount(['users', 'customers'])
            ->when($search !== '', function ($tenantQuery) use ($search) {
                $tenantQuery->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('subdomain', 'like', "%{$search}%")
                        ->orWhere('domain', 'like', "%{$search}%");
                });
            });

        $this->applyStatusFilter($query, $status);

        if (in_array($plan, Tenant::ALL_PLANS, true)) {
            $query->where('plan', $plan);
        } else {
            $plan = 'all';
        }

        match ($sort) {
            'name' => $query->orderBy('name'),
            'renewal' => $query->orderByRaw('COALESCE(subscription_ends_at, trial_ends_at) asc'),
            'last_seen' => $query->orderByDesc('last_seen_at'),
            'oldest' => $query->orderBy('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $tenants = $query->paginate(20)->withQueryString();
        $summary = $this->buildIndexSummary();
        $statusOptions = [
            'all' => 'All statuses',
            'attention' => 'Needs attention',
            'active' => 'Active',
            'suspended' => 'Suspended',
            'trial' => 'Trial',
            'expired' => 'Expired',
        ];
        $sortOptions = [
            'recent' => 'Newest first',
            'name' => 'Name',
            'renewal' => 'Renewal date',
            'last_seen' => 'Recent activity',
            'oldest' => 'Oldest first',
        ];
        $filters = compact('search', 'status', 'plan', 'sort');

        return view('admin.tenants.index', compact('tenants', 'summary', 'statusOptions', 'sortOptions', 'filters'));
    }

    /**
     * Show tenant details (Control Page).
     */
    public function show(Tenant $tenant): View
    {
        $planDefinition = Tenant::planDefinition($tenant->plan);

        // Section B: Usage & Value Summary
        // We use withoutGlobalScopes if needed, but Tenant model usually has local scopes.
        // Assuming relations are set up correctly on Tenant model.
        $metrics = [
            'users_count' => $tenant->users()->count(),
            'active_users_count' => $tenant->users()->where('is_active', true)->count(),
            'pending_invites_count' => $tenant->users()->whereNotNull('invite_token')->count(),
            'customers_count' => $tenant->customers()->count(),
            'vehicles_count' => $tenant->vehicles()->count(),
            'checkins_count' => $tenant->checkins()->count(),
            'workorders_count' => $tenant->workOrders()->count(),
            'current_month_workorders_count' => $tenant->workOrders()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'open_workorders_count' => $tenant->workOrders()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'stored_tires_count' => $tenant->tires()->where('status', 'stored')->count(),
            'invoices_count' => $tenant->hasMany(Invoice::class)->count(),
            'draft_invoices_count' => $tenant->hasMany(Invoice::class)->where('status', Invoice::STATUS_DRAFT)->count(),
            'paid_invoices_count' => $tenant->hasMany(Invoice::class)->where('status', Invoice::STATUS_PAID)->count(),
            'total_paid' => $tenant->hasMany(Invoice::class)->where('status', Invoice::STATUS_PAID)->sum('total'),
            'api_tokens_count' => $tenant->apiTokens()->count(),
            'active_api_tokens_count' => $tenant->apiTokens()->whereNull('revoked_at')->count(),
        ];

        // Owner Actions (excluding notes)
        $actionLogs = AuditLog::where('model_type', Tenant::class)
            ->where('model_id', $tenant->id)
            ->where('action', '!=', 'note')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Internal Notes (separate)
        $notes = AuditLog::where('model_type', Tenant::class)
            ->where('model_id', $tenant->id)
            ->where('action', 'note')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $planOptions = Tenant::planCatalog();
        $usageLimits = [
            'users' => $tenant->max_users ?? $planDefinition['limits']['max_users'],
            'customers' => $tenant->max_customers ?? $planDefinition['limits']['max_customers'],
            'vehicles' => $tenant->max_vehicles ?? $planDefinition['limits']['max_vehicles'],
            'work_orders' => $tenant->max_work_orders ?? $planDefinition['limits']['max_work_orders'],
        ];
        $tenantProfile = [
            'plan_definition' => $planDefinition,
            'notes_count' => $notes->count(),
            'action_count' => $actionLogs->count(),
            'last_seen_at' => $tenant->last_seen_at,
            'last_activity_at' => $tenant->last_activity_at,
            'renewal_date' => $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at,
        ];

        return view('admin.tenants.show', compact(
            'tenant',
            'metrics',
            'actionLogs',
            'notes',
            'planOptions',
            'usageLimits',
            'tenantProfile'
        ));
    }

    /**
     * Toggle tenant active status.
     */
    public function toggleActive(Tenant $tenant): RedirectResponse
    {
        $wasActive = $tenant->is_active;

        if ($wasActive) {
            $tenant->suspend();
            $action = 'suspended';
        } else {
            $tenant->activate();
            $action = 'activated';
        }

        $this->logAction($tenant, 'toggle_active', [
            'status_from' => $wasActive ? 'active' : 'suspended',
            'status_to' => $wasActive ? 'suspended' : 'active',
        ]);

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant has been {$action}.");
    }

    /**
     * Add bonus days to trial or subscription.
     */
    public function addBonusDays(AddBonusDaysRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();
        $days = (int) $validated['days'];
        $reason = $validated['reason'];

        $oldDate = $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
        $targetField = $tenant->is_trial ? 'trial_ends_at' : 'subscription_ends_at';

        // If date is null or past, start from now
        $baseDate = ($oldDate && $oldDate->isFuture()) ? $oldDate : now();
        $newDate = $baseDate->copy()->addDays($days);

        $tenant->update([$targetField => $newDate]);
        TenantCache::forgetTenant($tenant);

        $this->logAction($tenant, 'bonus_days', [
            'days_added' => $days,
            'old_date' => $oldDate ? $oldDate->format('Y-m-d') : 'N/A',
            'new_date' => $newDate->format('Y-m-d'),
            'reason' => $reason,
        ]);

        return back()->with('success', "Added {$days} bonus days. New end date: ".$newDate->format('d M Y'));
    }

    /**
     * Set the paid plan and renewal date for a tenant in the manual-billing workflow.
     */
    public function updateBilling(UpdateTenantBillingRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();

        $oldPlan = $tenant->plan;
        $oldRenewalDate = $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
        $renewalDate = ! empty($validated['renewal_date'])
            ? Carbon::parse($validated['renewal_date'])->endOfDay()
            : now()->addMonth()->endOfDay();

        $tenant->convertToSubscription($validated['plan'], $renewalDate);

        $settings = $tenant->settings ?? [];
        $settings['billing_mode'] = 'manual';
        $tenant->update(['settings' => $settings]);
        TenantCache::forgetTenant($tenant);

        $this->logAction($tenant, 'billing_update', [
            'reason' => $validated['reason'],
            'plan_from' => $oldPlan,
            'plan_to' => $validated['plan'],
            'renewal_from' => $oldRenewalDate?->toDateString(),
            'renewal_to' => $renewalDate->toDateString(),
            'restored_access' => true,
        ]);

        return back()->with(
            'success',
            "Billing updated. {$tenant->name} is now on the ".ucfirst($validated['plan']).' plan through '.$renewalDate->format('d M Y').'.'
        );
    }

    /**
     * Suspend tenant.
     */
    public function suspend(TenantReasonRequest $request, Tenant $tenant): RedirectResponse
    {
        if (! $tenant->is_active) {
            return back()->with('error', 'Tenant is already suspended.');
        }

        $tenant->suspend();

        $this->logAction($tenant, 'suspend', [
            'reason' => $request->validated()['reason'],
            'status_from' => 'active',
            'status_to' => 'suspended',
        ]);

        return back()->with('success', 'Tenant has been suspended.');
    }

    /**
     * Activate tenant.
     */
    public function activate(TenantReasonRequest $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->is_active) {
            return back()->with('error', 'Tenant is already active.');
        }

        $tenant->activate();

        $this->logAction($tenant, 'activate', [
            'reason' => $request->validated()['reason'],
            'status_from' => 'suspended',
            'status_to' => 'active',
        ]);

        return back()->with('success', 'Tenant has been activated.');
    }

    /**
     * Add internal note.
     */
    public function addNote(TenantNoteRequest $request, Tenant $tenant): RedirectResponse
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'note',
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
            'changes' => ['content' => $request->validated()['note']],
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Update internal note.
     */
    public function updateNote(TenantNoteRequest $request, Tenant $tenant, \App\Models\AuditLog $note): RedirectResponse
    {
        if ($note->model_id != $tenant->id || $note->action !== 'note') {
            abort(403);
        }

        $note->update([
            'changes' => ['content' => $request->validated()['note']],
        ]);

        return back()->with('success', 'Note updated.');
    }

    /**
     * Delete internal note.
     */
    public function deleteNote(Tenant $tenant, \App\Models\AuditLog $note): RedirectResponse
    {
        if ($note->model_id != $tenant->id || $note->action !== 'note') {
            abort(403);
        }

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /**
     * Archive a tenant and deactivate access from the admin panel.
     */
    public function destroy(ArchiveTenantRequest $request, Tenant $tenant, TenantLifecycleService $tenantLifecycleService): RedirectResponse
    {
        $tenantLifecycleService->archive($tenant, $request->user(), 'Archived from admin control page.');

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' has been archived and deactivated.");
    }

    /**
     * Helper to log owner actions.
     */
    private function logAction(Tenant $tenant, string $action, array $changes): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Apply the requested operational status filter to the tenant query.
     */
    private function applyStatusFilter($query, string &$status): void
    {
        $now = now();

        switch ($status) {
            case 'active':
                $query->where('is_active', true)->where(function ($activeQuery) use ($now) {
                    $activeQuery->where(function ($trialQuery) use ($now) {
                        $trialQuery->where('is_trial', true)->where(function ($innerQuery) use ($now) {
                            $innerQuery->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>=', $now);
                        });
                    })->orWhere(function ($subscriptionQuery) use ($now) {
                        $subscriptionQuery->where('is_trial', false)->where(function ($innerQuery) use ($now) {
                            $innerQuery->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '>=', $now);
                        });
                    });
                });
                break;
            case 'suspended':
                $query->where('is_active', false);
                break;
            case 'trial':
                $query->where('is_trial', true);
                break;
            case 'expired':
                $query->expired();
                break;
            case 'attention':
                $query->where(function ($attentionQuery) use ($now) {
                    $attentionQuery->where('is_active', false)
                        ->orWhere(function ($expiredQuery) use ($now) {
                            $expiredQuery->where('is_trial', true)->where('trial_ends_at', '<', $now);
                        })
                        ->orWhere(function ($expiredQuery) use ($now) {
                            $expiredQuery->where('is_trial', false)->whereNotNull('subscription_ends_at')->where('subscription_ends_at', '<', $now);
                        })
                        ->orWhere(function ($expiringQuery) use ($now) {
                            $expiringQuery->where('is_trial', true)->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)]);
                        })
                        ->orWhere(function ($expiringQuery) use ($now) {
                            $expiringQuery->where('is_trial', false)->whereBetween('subscription_ends_at', [$now, $now->copy()->addDays(7)]);
                        })
                        ->orWhere(function ($inactiveQuery) use ($now) {
                            $inactiveQuery->whereNotNull('last_seen_at')->where('last_seen_at', '<', $now->copy()->subDays(14));
                        });
                });
                break;
            default:
                $status = 'all';
        }
    }

    /**
     * Summary tiles for the tenant index page.
     */
    private function buildIndexSummary(): array
    {
        $now = now();

        return [
            'total' => Tenant::count(),
            'active' => Tenant::where('is_active', true)->count(),
            'suspended' => Tenant::where('is_active', false)->count(),
            'trial' => Tenant::where('is_trial', true)->count(),
            'expiring_soon' => Tenant::where(function ($query) use ($now) {
                $query->where(function ($trialQuery) use ($now) {
                    $trialQuery->where('is_trial', true)
                        ->whereBetween('trial_ends_at', [$now, $now->copy()->addDays(7)]);
                })->orWhere(function ($subscriptionQuery) use ($now) {
                    $subscriptionQuery->where('is_trial', false)
                        ->whereBetween('subscription_ends_at', [$now, $now->copy()->addDays(7)]);
                });
            })->count(),
        ];
    }
}
