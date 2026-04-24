<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.smooth');

        // C2 (sprint 2026-04-24): register the hashed user provider so
        // `remember_token` stored in the DB is SHA-256 of the cookie
        // value. See app/Auth/HashedEloquentUserProvider.php. config/auth.php
        // uses driver `hashed-eloquent` for the `users` provider.
        \Illuminate\Support\Facades\Auth::provider('hashed-eloquent', function ($app, array $config) {
            return new \App\Auth\HashedEloquentUserProvider(
                $app['hash'],
                $config['model'],
            );
        });

        // Security review L-1: tighten the default password rule across the
        // entire app. Every call site using `Password::defaults()` now gets
        // the hardened rule automatically; sites still using `min:8` string
        // rules (ManagementController, InviteController) were migrated to
        // this helper in the same PR.
        //
        // `uncompromised()` talks to haveibeenpwned.com via k-anonymity. We
        // skip it outside production so CI and local dev do not depend on
        // that network round-trip; production gets the full check.
        \Illuminate\Validation\Rules\Password::defaults(function () {
            $rule = \Illuminate\Validation\Rules\Password::min(12)
                ->mixedCase()
                ->numbers();

            return $this->app->environment('production') ? $rule->uncompromised() : $rule;
        });

        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');

            // Fail loudly if APP_DEBUG leaked into production — stack traces
            // and env dumps in error pages are a high-severity info leak.
            if (config('app.debug')) {
                throw new \RuntimeException(
                    'APP_DEBUG must not be true in production. Refusing to boot.'
                );
            }

            /*
             * Bug review OPS-04: scheduler `->onOneServer()` locks live in
             * the cache store. If cache.default is `array` or `file` in a
             * multi-container deploy, every container runs every command
             * (no lock coordination). That means backups run N times,
             * audit archival deletes rows N times, notifications fire N
             * times. We refuse to boot production unless an atomic cache
             * store is configured.
             */
            $cacheStore = (string) config('cache.default');
            $atomicStores = ['redis', 'memcached', 'database', 'dynamodb'];
            if (! in_array($cacheStore, $atomicStores, true)) {
                throw new \RuntimeException(
                    "Cache store '{$cacheStore}' cannot back scheduler ->onOneServer() locks. "
                    .'Production requires one of: '.implode(', ', $atomicStores).'. '
                    .'Set CACHE_STORE=redis (recommended) in the environment.'
                );
            }

            /*
             * Bug review OPS-10: refuse to boot production with an
             * unauthenticated Redis connection. The default .env has
             * REDIS_PASSWORD=null which ships fine for local dev but is a
             * gift to any lateral-movement attacker in production — session
             * cookies, cache entries, and queue jobs live in Redis.
             *
             * We don't hard-fail if Redis isn't even configured (some
             * tenants may run on a different cache backend), only if Redis
             * IS in use and has no password.
             */
            if ($cacheStore === 'redis' || config('session.driver') === 'redis' || config('queue.default') === 'redis') {
                $redisPass = (string) config('database.redis.default.password');
                // Laravel interprets string "null" as the literal string, not null.
                if ($redisPass === '' || $redisPass === 'null') {
                    throw new \RuntimeException(
                        'REDIS_PASSWORD is not set in production. Redis is used as a cache / '
                        .'session / queue backend and an unauthenticated Redis on a shared '
                        .'network is a P1 security issue. Set REDIS_PASSWORD to a strong '
                        .'value (and configure the Redis server with --requirepass).'
                    );
                }
            }
        }

        // S-07: resolve auto-login eligibility ONCE at boot instead of probing
        // the filesystem on every request. That kills a subtle timing side
        // channel (file_exists() latency deltas on shared filesystems) and
        // makes the invariant "auto-login can never run outside local" a
        // compile-time style guarantee rather than a middleware convention.
        config()->set('app.auto_login_verified', \App\Support\AutoLoginGuard::resolve());

        /*
         * Bug review OPS-23: two-layer login rate limit.
         *
         *   Layer 1 (per email+IP): 5/min — stops targeted password guessing
         *     against a known account.
         *   Layer 2 (per IP): 20/min — stops wide attacks that rotate through
         *     many emails from the same source. Without this, a botnet could
         *     try 5 guesses × 1000 emails/min from one IP.
         *
         * Laravel picks the most restrictive limit automatically when an
         * array is returned from the RateLimiter::for callback.
         */
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by('login:'.$request->input('email', '').'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('tenant-api', function (Request $request) {
            $limit = tenant()?->api_rate_limit ?? 60;
            $identifier = $request->attributes->get('tenant_api_token_id')
                ?? $request->attributes->get('tenant_api_token_prefix')
                ?? $request->ip();

            return Limit::perMinute((int) $limit)->by('tenant-api:'.$identifier);
        });

        // Register observers
        \App\Models\Customer::observe(\App\Observers\CustomerObserver::class);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        // Define Gates for Role-Based Access Control
        \Illuminate\Support\Facades\Gate::define('view-financials', function ($user) {
            return $user->can(\App\Enums\Permission::ACCESS_FINANCE);
        });

        \Illuminate\Support\Facades\Gate::define('delete-records', function ($user) {
            return $user->can(\App\Enums\Permission::DELETE_RECORDS);
        });

        \Illuminate\Support\Facades\Gate::define('perform-admin-actions', function ($user) {
            return $user->hasRole('admin');
        });

        // Register Model Policies
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Invoice::class, \App\Policies\InvoicePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Customer::class, \App\Policies\CustomerPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Vehicle::class, \App\Policies\VehiclePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Checkin::class, \App\Policies\CheckinPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\WorkOrder::class, \App\Policies\WorkOrderPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Tire::class, \App\Policies\TirePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Product::class, \App\Policies\ProductPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Appointment::class, \App\Policies\AppointmentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Service::class, \App\Policies\ServicePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\WorkOrderPhoto::class, \App\Policies\WorkOrderPhotoPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\Quote::class, \App\Policies\QuotePolicy::class);

        // D-07: any backup failure also lands in Sentry (on top of the
        // e-mail notification already wired in config/backup.php).
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\ReportBackupFailure::class);

        /*
         * Bug review OPS-07: slow-query logging via PHP rather than PG.
         *
         * The previous attempt sent `-c log_min_duration_statement=...` to
         * PDO's `options` array, which PDO silently ignores for pgsql.
         * Hooking DB::listen catches every query regardless of driver,
         * works uniformly in dev/test/prod, and doesn't require
         * CREATE EXTENSION or superuser privileges.
         *
         * Controlled by DB_SLOW_QUERY_LOG_MS (default unset = disabled).
         * Recommended production value: 500. Logs go to the app logger
         * under the 'slow_query' channel so ops can filter them.
         */
        $threshold = (int) env('DB_SLOW_QUERY_LOG_MS', 0);
        if ($threshold > 0) {
            \Illuminate\Support\Facades\DB::listen(function ($query) use ($threshold) {
                if ($query->time >= $threshold) {
                    \Illuminate\Support\Facades\Log::warning('slow_query', [
                        'connection' => $query->connectionName,
                        'time_ms' => round($query->time, 1),
                        'sql' => $query->sql,
                        // Security review M-7: do NOT log raw bindings. A slow
                        // query's bindings routinely include customer phone,
                        // email, name, IBAN, invoice amounts — all PII.
                        // Scrub strings to `str(<len>)` so ops keep type +
                        // cardinality signal without leaking values. Numbers
                        // and booleans are kept since they are rarely PII and
                        // are useful for tuning. Instances (e.g. Carbon) are
                        // stringified via get_class().
                        'bindings' => self::scrubSlowQueryBindings($query->bindings),
                        'tenant_id' => function_exists('tenant_id') ? tenant_id() : null,
                    ]);
                }
            });
        }
    }

    /**
     * Replace bindings that may contain PII (strings, objects) with a shape
     * marker. Used for slow-query logging — see the DB::listen() block above.
     *
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    private static function scrubSlowQueryBindings(array $bindings): array
    {
        $scrubbed = [];
        foreach ($bindings as $key => $value) {
            if (is_string($value)) {
                $scrubbed[$key] = 'str('.strlen($value).')';
            } elseif (is_object($value)) {
                $scrubbed[$key] = 'obj('.get_class($value).')';
            } elseif (is_array($value)) {
                $scrubbed[$key] = 'arr('.count($value).')';
            } else {
                // int, float, bool, null — safe to keep.
                $scrubbed[$key] = $value;
            }
        }

        return $scrubbed;
    }
}
