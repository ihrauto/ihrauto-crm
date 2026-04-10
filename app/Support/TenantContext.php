<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

class TenantContext
{
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
        return $this->current()?->id ?? auth()->user()?->tenant_id;
    }

    public function apiToken(): ?TenantApiToken
    {
        return App::bound('tenant_api_token') ? App::make('tenant_api_token') : null;
    }

    public function set(Tenant $tenant, ?Request $request = null, ?TenantApiToken $token = null): void
    {
        App::instance('tenant', $tenant);
        Config::set('tenant', $tenant->toArray());

        if ($token) {
            App::instance('tenant_api_token', $token);
        }

        if ($request && $request->hasSession()) {
            $request->session()->put('tenant_id', $tenant->id);
        }

        Config::set('app.name', $tenant->name);
        Config::set('app.timezone', $tenant->timezone);
        Config::set('app.locale', $tenant->locale);

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
