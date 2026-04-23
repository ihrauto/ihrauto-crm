<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class TenantContext
{
    /**
     * Scalability C-4: memoize the auth-user fallback so repeated id()
     * calls inside a single request don't trigger a fresh User lookup
     * each time. Reset by clear() to keep per-test isolation.
     */
    private ?int $fallbackTenantId = null;

    private bool $fallbackResolved = false;

    public function current(): ?Tenant
    {
        return App::bound('tenant') ? App::make('tenant') : null;
    }

    /**
     * Return the current tenant ID, or the authenticated user's tenant_id as a fallback.
     *
     * ARCHITECTURAL NOTE: the fallback to auth()->user()?->tenant_id is REQUIRED, not
     * optional. Laravel's SubstituteBindings middleware (which performs route-model
     * binding) runs BEFORE our TenantMiddleware is invoked, because TenantMiddleware
     * is appended to the web group. During SubstituteBindings, `current()` returns
     * null — without this fallback, TenantScope does not apply to route-bound models,
     * enabling cross-tenant reads through route parameters (a bound Customer could
     * belong to a different tenant than the authenticated user's).
     *
     * The fallback is safe because:
     *   - An authenticated user's `tenant_id` is authoritative and cannot be forged
     *     (the session cookie is signed and HTTP-only).
     *   - `actingAs()` in tests sets the auth user before the request, so the fallback
     *     works in test contexts too.
     *
     * For background jobs and console commands that need a tenant context, use
     * `TenantContext::set()` explicitly — the fallback only applies when there is
     * an authenticated web user.
     */
    public function id(): ?int
    {
        // Fast path: tenant context already set by middleware.
        if ($current = $this->current()) {
            return $current->id;
        }

        // Fallback path: derive from the authenticated user. Memoized
        // so route-model binding (which calls this for every bound
        // model) doesn't re-query the User row on every call.
        if (! $this->fallbackResolved) {
            $this->fallbackTenantId = auth()->user()?->tenant_id;
            $this->fallbackResolved = true;
        }

        return $this->fallbackTenantId;
    }

    public function apiToken(): ?TenantApiToken
    {
        return App::bound('tenant_api_token') ? App::make('tenant_api_token') : null;
    }

    public function set(Tenant $tenant, ?Request $request = null, ?TenantApiToken $token = null): void
    {
        // S-12 defense-in-depth: inactive tenants should never be bound as
        // the request's tenant context. Upstream middleware (TenantMiddleware,
        // AuthenticateTenantApiToken) already rejects inactive tenants before
        // reaching here — this guard catches any future caller that forgets.
        //
        // Superadmin flows that need to touch suspended tenants should use
        // `withoutGlobalScopes()` directly and NOT bind a tenant context.
        if (! $tenant->is_active) {
            throw new \RuntimeException(
                'Refusing to bind inactive tenant to request context.'
            );
        }

        App::instance('tenant', $tenant);
        Config::set('tenant', $tenant->toArray());

        if ($token) {
            App::instance('tenant_api_token', $token);
        }

        if ($request && $request->hasSession()) {
            $request->session()->put('tenant_id', $tenant->id);
        }

        Config::set('app.name', $tenant->name);
        Config::set('app.locale', $tenant->locale);

        // INTENTIONAL: do NOT set app.timezone per tenant.
        //
        // All datetimes are stored in UTC (config/app.php sets the default).
        // Overriding app.timezone here would make Eloquent write timestamps in
        // the tenant's timezone, which breaks when:
        //   - A tenant changes their timezone (historical data becomes wrong)
        //   - A background job runs without a tenant context
        //   - Data is queried via direct SQL
        //
        // Views and API responses should convert to the tenant timezone at
        // display time using Carbon's setTimezone($tenant->timezone).

        if ($tenant->database_name) {
            $this->configureTenantDatabase($tenant);
        }
    }

    public function clear(): void
    {
        if (App::bound('tenant')) {
            App::forgetInstance('tenant');
        }

        if (App::bound('tenant_api_token')) {
            App::forgetInstance('tenant_api_token');
        }

        Config::set('tenant', null);

        // Scalability C-4: reset the memoized fallback so tests and
        // subsequent requests (under rare re-used worker scenarios)
        // re-resolve instead of seeing stale state.
        $this->fallbackTenantId = null;
        $this->fallbackResolved = false;
    }

    private function configureTenantDatabase(Tenant $tenant): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => $tenant->database_name,
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);
    }
}
