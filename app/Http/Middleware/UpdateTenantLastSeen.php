<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateTenantLastSeen
{
    /**
     * Handle an incoming request.
     *
     * Updates the tenant's last_seen_at timestamp if:
     * - User is authenticated
     * - User has a tenant
     * - last_seen_at is null OR older than 5 minutes (throttled to reduce DB writes)
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->tenant) {
            $tenant = auth()->user()->tenant;

            // Throttle: only update if null or older than 5 minutes
            if (!$tenant->last_seen_at || $tenant->last_seen_at->lt(now()->subMinutes(5))) {
                $tenant->update(['last_seen_at' => now()]);
            }
        }

        return $next($request);
    }
}
