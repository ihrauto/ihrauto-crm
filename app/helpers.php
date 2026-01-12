<?php

if (! function_exists('tenant')) {
    /**
     * Get the current tenant
     *
     * @return \App\Models\Tenant|null
     */
    function tenant()
    {
        return app()->bound('tenant') ? app('tenant') : null;
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
        $tenant = tenant();

        return $tenant ? $tenant->id : null;
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
