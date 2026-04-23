<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    /*
     * In production, set CORS_ALLOWED_ORIGINS to a comma-separated list
     * of trusted frontend domains, e.g. "https://app.ihrauto.ch,https://admin.ihrauto.ch"
     * Defaults to APP_URL if not set.
     *
     * SECURITY: fail-closed in production. If CORS_ALLOWED_ORIGINS is not set
     * and APP_URL is missing, we return an empty allowlist (blocks all cross-
     * origin requests) rather than the legacy wildcard "*" fallback, which
     * would otherwise expose the API to any origin.
     *
     * env() is used (not app()->environment()) because config files are
     * loaded before the application container is bootstrapped.
     */
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', env('APP_URL', env('APP_ENV') === 'production' ? '' : '*'))
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
