<?php

namespace App\Support;

/**
 * S-07: triple-gate resolution for auto-login.
 *
 * The original implementation ran all three checks (env + marker file +
 * config flag) inside TenantMiddleware on every request. That leaked a
 * subtle timing side channel (file_exists() latency) and also duplicated
 * the logic across middleware + tests.
 *
 * Centralising it here gives us:
 *   - one place to reason about the invariant
 *   - a boot-time cache so requests never probe the filesystem
 *   - unit-testable behaviour without constructing the middleware
 */
class AutoLoginGuard
{
    /**
     * Resolve the triple-gate at *this* moment and return whether auto-login
     * is allowed. Called once from AppServiceProvider::boot().
     */
    public static function resolve(): bool
    {
        return app()->environment('local')
            && (bool) config('app.auto_login_enabled', false)
            && is_file(storage_path('app/.auto_login_enabled'));
    }

    /**
     * Return the cached flag (or `false` when never resolved). Middleware
     * reads this per request; it never touches the filesystem or env vars
     * after boot.
     */
    public static function verified(): bool
    {
        return (bool) config('app.auto_login_verified', false);
    }
}
