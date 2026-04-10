<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip tenant resolution for development routes
        if ($this->isDevelopmentRoute($request)) {
            return $next($request);
        }

        // Tenant context is request-scoped. Clear any stale bindings before resolving again.
        $this->tenantContext->clear();

        // Superadmins bypass tenant middleware entirely
        try {
            if (Auth::check() && Auth::user()->hasRole('super-admin')) {
                return $next($request);
            }
        } catch (\Exception $e) {
            // Roles table may not exist yet - continue with normal flow
        }

        $tenant = $this->resolveTenant($request);

        if (! $tenant) {
            // If no tenant found, redirect to main site or return 404
            return $this->handleMissingTenant($request);
        }

        // Check if tenant is active
        if (! $tenant->is_active) {
            return $this->handleInactiveTenant($request, $tenant);
        }

        // Check if tenant subscription is expired
        if ($tenant->is_expired && ! $this->allowsExpiredTenantAccess($request)) {
            return $this->handleExpiredTenant($request, $tenant);
        }

        // Set tenant context
        $this->tenantContext->set($tenant, $request);

        // Auto-login for local development ONLY.
        //
        // SECURITY: triple-gated to make accidental production enablement impossible:
        //   1. APP_ENV must be 'local' (not testing, not staging, not production)
        //   2. A physical marker file storage/app/.auto_login_enabled must exist.
        //      This file is gitignored and cannot be shipped in a Docker image
        //      or deployment artifact.
        //   3. AUTO_LOGIN_ENABLED config flag must be true (legacy belt-and-suspenders).
        //
        // The marker file requirement defeats config misconfiguration: even if
        // someone sets AUTO_LOGIN_ENABLED=true in a production .env, no marker
        // file exists on disk, so auto-login stays off.
        if ($this->shouldAutoLogin() && ! Auth::check()) {
            $user = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
            if ($user) {
                Auth::login($user);
            }
        }

        return $next($request);
    }

    /**
     * Triple-check whether auto-login is allowed: env + marker file + config flag.
     */
    private function shouldAutoLogin(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if (! file_exists(storage_path('app/.auto_login_enabled'))) {
            return false;
        }

        return (bool) config('app.auto_login_enabled', false);
    }

    /**
     * Resolve tenant from request
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $tenant = $this->tenantContext->current()
            ?? $this->getTenantFromRoute($request)
            ?? $this->getTenantFromSubdomain($request)
            ?? $this->getTenantFromDomain($request)
            ?? $this->getTenantFromSession($request)
            ?? $this->getTenantFromAuth($request);

        return $tenant;
    }

    /**
     * Get tenant from authenticated user
     */
    private function getTenantFromAuth(Request $request): ?Tenant
    {
        if (Auth::check() && Auth::user()->tenant_id) {
            $tenantId = Auth::user()->tenant_id;

            return Cache::remember(TenantCache::tenantIdKey($tenantId), 3600, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }

        return null;
    }

    /**
     * Get tenant from subdomain (tenant.yourapp.com)
     */
    private function getTenantFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Check if it's a subdomain (more than 2 parts)
        if (count($parts) >= 3) {
            $subdomain = $parts[0];

            // Skip www and common subdomains
            if (in_array($subdomain, ['www', 'api', 'admin', 'mail'])) {
                return null;
            }

            return Cache::remember(TenantCache::tenantSubdomainKey($subdomain), 3600, function () use ($subdomain) {
                return Tenant::where('subdomain', $subdomain)->active()->first();
            });
        }

        return null;
    }

    /**
     * Get tenant from route parameter
     */
    private function getTenantFromRoute(Request $request): ?Tenant
    {
        $tenantId = $request->route('tenant');

        if ($tenantId && is_numeric($tenantId)) {
            return Cache::remember(TenantCache::tenantIdKey($tenantId), 3600, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }

        // Handle if route param is a model instance
        if ($tenantId instanceof Tenant) {
            return $tenantId;
        }

        return null;
    }

    /**
     * Get tenant from custom domain
     */
    private function getTenantFromDomain(Request $request): ?Tenant
    {
        $host = $request->getHost();

        return Cache::remember(TenantCache::tenantDomainKey($host), 3600, function () use ($host) {
            return Tenant::where('domain', $host)->active()->first();
        });
    }

    /**
     * Get tenant from session (for admin switching)
     */
    private function getTenantFromSession(Request $request): ?Tenant
    {
        $tenantId = session('tenant_id');

        if ($tenantId) {
            return Cache::remember(TenantCache::tenantIdKey($tenantId), 3600, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }

        return null;
    }

    /**
     * Handle missing tenant
     */
    private function handleMissingTenant(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'The requested tenant could not be found.',
            ], 404);
        }

        // In local development, redirect to tenant switcher
        if (app()->environment('local')) {
            return redirect()->route('dev.tenant-switch')->with('error', 'Please select a tenant to continue.');
        }

        // In production:
        // If user is logged in but tenant cannot be resolved, send them to company creation/selection
        if (Auth::check()) {
            return redirect()->route('auth.create-company')
                ->with('error', 'No company/tenant selected. Please create or select a company to continue.');
        }

        // Guest -> login
        return redirect()->route('login');
    }

    /**
     * Handle inactive tenant
     */
    private function handleInactiveTenant(Request $request, Tenant $tenant): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant inactive',
                'message' => 'This tenant account is currently inactive.',
            ], 403);
        }

        return response()->view('errors.tenant-inactive', compact('tenant'), 403);
    }

    /**
     * Handle expired tenant
     */
    private function handleExpiredTenant(Request $request, Tenant $tenant): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Tenant expired',
                'message' => 'This tenant subscription has expired.',
                'trial_expired' => $tenant->is_trial,
                'expires_at' => $tenant->is_trial ? $tenant->trial_ends_at : $tenant->subscription_ends_at,
            ], 403);
        }

        if (Auth::check()) {
            return redirect()->route('billing.pricing')
                ->with('warning', 'Your trial or subscription has expired. Review plans and contact billing to restore access.');
        }

        return response()->view('errors.tenant-expired', compact('tenant'), 403);
    }

    /**
     * Check if this is a development route that doesn't require tenant
     */
    private function isDevelopmentRoute(Request $request): bool
    {
        $path = trim($request->getPathInfo(), '/');

        // IMPORTANT: root landing page must bypass tenant resolution
        if ($path === '') {
            return true;
        }

        // Auth routes should always be accessible without tenant
        $authRoutes = [
            'health',
            'login',
            'register',
            'logout',
            'forgot-password',
            'reset-password',
            'verify-email',
            'email/verification-notification',
            'confirm-password',
            'auth/google',
            'auth/google/callback',
            'auth/create-company',
            'invite',
        ];

        foreach ($authRoutes as $authRoute) {
            if (str_starts_with($path, $authRoute)) {
                return true;
            }
        }

        // Development-only routes
        if (! app()->environment('local')) {
            return false;
        }

        $developmentRoutes = [
            'dev/tenant-switch',
            'dev/tenant-info',
            'dev/tenant-clear',
            'subscription/checkout',
            'subscription/process',
            // 'subscription/onboarding' must run through middleware to resolve tenant from session
        ];

        foreach ($developmentRoutes as $devRoute) {
            if (str_starts_with($path, $devRoute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Allow expired tenants to reach the manual billing page so they can recover access.
     */
    private function allowsExpiredTenantAccess(Request $request): bool
    {
        $path = trim($request->getPathInfo(), '/');

        return $request->routeIs('billing.pricing') || $path === 'billing/plans';
    }
}
