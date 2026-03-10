<?php

use App\Support\TenantContext;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant
     *
     * @return \App\Models\Tenant|null
     */
    function tenant()
    {
        return app(TenantContext::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant ID
     *
     * @return int|null
     */
    function tenant_id()
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('tenant_api_token')) {
    /**
     * Get the current tenant API token.
     */
    function tenant_api_token()
    {
        return app(TenantContext::class)->apiToken();
    }
}

if (! function_exists('app_version')) {
    /**
     * Get the application version
     *
     * @return string
     */
    function app_version()
    {
        return config('app.version', '1.0.0');
    }
}
