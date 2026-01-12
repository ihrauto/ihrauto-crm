<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantSwitchController extends Controller
{
    /**
     * Show tenant switcher (only for local development)
     */
    public function index()
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $tenants = Tenant::active()->get();
        $currentTenant = tenant();

        // If no global tenant context but user is logged in, try to get tenant from user
        if (! $currentTenant && Auth::check() && Auth::user()->tenant_id) {
            $currentTenant = Tenant::find(Auth::user()->tenant_id);
        }

        return view('dev.tenant-switch', compact('tenants', 'currentTenant'));
    }

    /**
     * Switch to a specific tenant
     */
    public function switch(Request $request, $tenantId)
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $tenant = Tenant::findOrFail($tenantId);

        // Set tenant in session for development
        session(['tenant_id' => $tenant->id]);

        // Auto-login as the first user of this tenant for development
        $user = User::where('tenant_id', $tenant->id)->first();
        if ($user) {
            Auth::login($user);
        }

        return redirect()->route('dashboard')->with('success', "Switched to tenant: {$tenant->name}".($user ? " (logged in as {$user->name})" : ''));
    }

    /**
     * Clear tenant (for testing no-tenant state)
     */
    public function clear()
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        session()->forget('tenant_id');

        if (Auth::check()) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }

        return redirect()->route('dev.tenant-switch')->with('success', 'Tenant context cleared');
    }

    /**
     * Get tenant info as JSON (for debugging)
     */
    public function info()
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $tenant = tenant();

        return response()->json([
            'current_tenant' => $tenant ? $tenant->toArray() : null,
            'session_tenant_id' => session('tenant_id'),
            'all_tenants' => Tenant::active()->get(['id', 'name', 'subdomain', 'plan'])->toArray(),
        ]);
    }
}
