<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTireHotelAccess
{
    /**
     * Handle an incoming request.
     *
     * Tire Hotel is only available for STANDARD and CUSTOM plans.
     * BASIC plan users are redirected with an upgrade message.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            return redirect()->route('dev.tenant-switch')
                ->with('error', 'Please select a tenant to continue.');
        }

        // Check if tenant has Tire Hotel access (STANDARD or CUSTOM plans)
        if (! $tenant->hasTireHotel()) {
            return redirect()->route('dashboard')->with(
                'error',
                'Tire Hotel is not available on the Basic plan. Upgrade to Standard to unlock this feature.'
            );
        }

        return $next($request);
    }
}
