<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->can($permission)) {
            abort(403, 'You do not have permission to access this module.');
        }

        $tenant = tenant();
        $featureMap = [
            'access check-in' => 'vehicle_checkin',
            'access tire-hotel' => 'tire_hotel',
        ];

        if ($tenant && isset($featureMap[$permission]) && ! $tenant->hasFeature($featureMap[$permission])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Module disabled',
                    'message' => 'This module is disabled for the current tenant.',
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', 'This module is disabled for your company.');
        }

        return $next($request);
    }
}
