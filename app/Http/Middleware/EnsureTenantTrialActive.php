<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantTrialActive
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has a valid, active tenant with an active trial/subscription.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Superadmins bypass tenant checks (they may have tenant_id = null)
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // User must have a tenant
        if (! $user->tenant_id || ! $user->tenant) {
            abort(403, 'No tenant associated with this account.');
        }

        $tenant = $user->tenant;

        // Tenant must be active
        if (! $tenant->is_active) {
            abort(403, 'Your account has been suspended. Please contact support.');
        }

        // Check trial/subscription expiry
        if ($tenant->is_expired) {
            return redirect()->route('management.pricing')
                ->with('warning', 'Your trial has expired. Please choose a subscription plan to continue.');
        }

        return $next($request);
    }
}
