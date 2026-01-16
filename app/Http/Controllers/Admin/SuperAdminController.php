<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminController extends Controller
{
    /**
     * Display a listing of all tenants.
     */
    public function index(): View
    {
        $tenants = Tenant::withCount('users')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.tenants.index', compact('tenants'));
    }

    /**
     * Show tenant details (Control Page).
     */
    public function show(Tenant $tenant): View
    {
        // Section B: Usage & Value Summary
        // We use withoutGlobalScopes if needed, but Tenant model usually has local scopes.
        // Assuming relations are set up correctly on Tenant model.
        $metrics = [
            'users_count' => $tenant->users()->count(),
            'customers_count' => $tenant->customers()->count(),
            'vehicles_count' => $tenant->vehicles()->count(),
            'checkins_count' => $tenant->checkins()->count(),
            'workorders_count' => $tenant->workOrders()->count(),
            'invoices_count' => $tenant->hasMany(\App\Models\Invoice::class)->count(),
            'total_paid' => $tenant->hasMany(\App\Models\Invoice::class)->where('status', 'paid')->sum('total'),
        ];

        // Owner Actions (excluding notes)
        $actionLogs = \App\Models\AuditLog::where('model_type', Tenant::class)
            ->where('model_id', $tenant->id)
            ->where('action', '!=', 'note')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Internal Notes (separate)
        $notes = \App\Models\AuditLog::where('model_type', Tenant::class)
            ->where('model_id', $tenant->id)
            ->where('action', 'note')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.tenants.show', compact('tenant', 'metrics', 'actionLogs', 'notes'));
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
    public function addBonusDays(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:365',
            'reason' => 'required|string|max:255',
        ]);

        $days = (int) $request->days;
        $reason = $request->reason;

        $oldDate = $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at;
        $targetField = $tenant->is_trial ? 'trial_ends_at' : 'subscription_ends_at';

        // If date is null or past, start from now
        $baseDate = ($oldDate && $oldDate->isFuture()) ? $oldDate : now();
        $newDate = $baseDate->copy()->addDays($days);

        $tenant->update([$targetField => $newDate]);

        $this->logAction($tenant, 'bonus_days', [
            'days_added' => $days,
            'old_date' => $oldDate ? $oldDate->format('Y-m-d') : 'N/A',
            'new_date' => $newDate->format('Y-m-d'),
            'reason' => $reason
        ]);

        return back()->with('success', "Added {$days} bonus days. New end date: " . $newDate->format('d M Y'));
    }

    /**
     * Suspend tenant.
     */
    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if (!$tenant->is_active) {
            return back()->with('error', 'Tenant is already suspended.');
        }

        $tenant->suspend();

        $this->logAction($tenant, 'suspend', [
            'reason' => $request->reason,
            'status_from' => 'active',
            'status_to' => 'suspended'
        ]);

        return back()->with('success', 'Tenant has been suspended.');
    }

    /**
     * Activate tenant.
     */
    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        if ($tenant->is_active) {
            return back()->with('error', 'Tenant is already active.');
        }

        $tenant->activate();

        $this->logAction($tenant, 'activate', [
            'reason' => $request->reason,
            'status_from' => 'suspended',
            'status_to' => 'active'
        ]);

        return back()->with('success', 'Tenant has been activated.');
    }

    /**
     * Add internal note.
     */
    public function addNote(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'note',
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
            'changes' => ['content' => $request->note],
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'Note added.');
    }

    /**
     * Update internal note.
     */
    public function updateNote(Request $request, Tenant $tenant, \App\Models\AuditLog $note): RedirectResponse
    {
        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        if ($note->model_id != $tenant->id || $note->action !== 'note') {
            abort(403);
        }

        $note->update([
            'changes' => ['content' => $request->note],
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
     * Permanently delete a tenant and all associated data.
     */
    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        $request->validate([
            'confirmation' => 'required|in:DELETE',
        ], [
            'confirmation.in' => 'Please type DELETE to confirm.',
        ]);

        $tenantName = $tenant->name;
        $tenantId = $tenant->id;

        // Delete all users associated with this tenant
        \DB::table('users')->where('tenant_id', $tenantId)->delete();

        // Delete all related data (cascade from tenant)
        // Most related models should cascade delete via foreign keys, but we'll be explicit
        $tenant->delete();

        $this->logAction($tenant, 'delete', [
            'tenant_name' => $tenantName,
            'deleted_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('admin.tenants.index')
            ->with('success', "Tenant '{$tenantName}' and all associated data have been permanently deleted.");
    }

    /**
     * Helper to log owner actions.
     */
    private function logAction(Tenant $tenant, string $action, array $changes): void
    {
        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => Tenant::class,
            'model_id' => $tenant->id,
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }
}

