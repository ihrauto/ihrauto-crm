<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
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

        // Superadmins bypass tenant middleware entirely
        if (Auth::check() && Auth::user()->hasRole('super-admin')) {
            return $next($request);
        }

        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            // If no tenant found, redirect to main site or return 404
            return $this->handleMissingTenant($request);
        }

        // Check if tenant is active
        if (!$tenant->is_active) {
            return $this->handleInactiveTenant($request, $tenant);
        }

        // Check if tenant subscription is expired
        if ($tenant->is_expired) {
            return $this->handleExpiredTenant($request, $tenant);
        }

        // Set tenant context
        $this->setTenantContext($tenant);

        // In local development, auto-login as first tenant user if not authenticated
        if (app()->environment('local') && !Auth::check()) {
            // Use withoutGlobalScopes to prevent TenantScope infinite loop
            $user = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
            if ($user) {
                Auth::login($user);
            }
        }

        // Update last activity
        $tenant->updateLastActivity();

        return $next($request);
    }

    /**
     * Resolve tenant from request
     */
    private function resolveTenant(Request $request): ?Tenant
    {
        $tenant = $this->getTenantFromRoute($request)
            ?? $this->getTenantFromSubdomain($request)
            ?? $this->getTenantFromDomain($request)
            ?? $this->getTenantFromHeader($request)
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

            return Cache::remember("tenant.id.{$tenantId}", 3600, function () use ($tenantId) {
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

            return Cache::remember("tenant.subdomain.{$subdomain}", 3600, function () use ($subdomain) {
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
            return Cache::remember("tenant.id.{$tenantId}", 3600, function () use ($tenantId) {
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

        return Cache::remember("tenant.domain.{$host}", 3600, function () use ($host) {
            return Tenant::where('domain', $host)->active()->first();
        });
    }

    /**
     * Get tenant from API header (X-Tenant-ID or X-Tenant-Slug)
     */
    private function getTenantFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');
        $tenantSlug = $request->header('X-Tenant-Slug');

        if ($tenantId) {
            return Cache::remember("tenant.id.{$tenantId}", 3600, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }

        if ($tenantSlug) {
            return Cache::remember("tenant.slug.{$tenantSlug}", 3600, function () use ($tenantSlug) {
                return Tenant::where('slug', $tenantSlug)->active()->first();
            });
        }

        return null;
    }

    /**
     * Get tenant from session (for admin switching)
     */
    private function getTenantFromSession(Request $request): ?Tenant
    {
        $tenantId = session('tenant_id');

        if ($tenantId) {
            return Cache::remember("tenant.id.{$tenantId}", 3600, function () use ($tenantId) {
                return Tenant::find($tenantId);
            });
        }

        return null;
    }

    /**
     * Set tenant context in application
     */
    private function setTenantContext(Tenant $tenant): void
    {
        // Set tenant in app container
        App::instance('tenant', $tenant);

        // Set tenant in config
        Config::set('tenant', $tenant->toArray());

        // Set tenant in session
        session(['tenant_id' => $tenant->id]);

        // Set application settings from tenant
        Config::set('app.name', $tenant->name);
        Config::set('app.timezone', $tenant->timezone);
        Config::set('app.locale', $tenant->locale);

        // Set database connection if using database-per-tenant
        if ($tenant->database_name) {
            $this->setTenantDatabase($tenant);
        }
    }

    /**
     * Set tenant-specific database connection
     */
    private function setTenantDatabase(Tenant $tenant): void
    {
        $databaseName = $tenant->database_name;

        Config::set('database.connections.tenant', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $databaseName,
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        // IMPORTANT: Do NOT switch database.default here!
        // This breaks sessions when SESSION_DRIVER=database because Laravel writes sessions
        // to the 'default' connection, causing auth state to be lost between requests.
        // Models should use $connection = 'tenant' explicitly if needed.
        // Config::set('database.default', 'tenant');
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
        ];

        foreach ($authRoutes as $authRoute) {
            if (str_starts_with($path, $authRoute)) {
                return true;
            }
        }

        // Development-only routes
        if (!app()->environment('local')) {
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
}

// Helper function to get current tenant
if (!function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app('tenant');
    }
}
