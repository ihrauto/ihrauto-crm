<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies for Render
        $middleware->append(\App\Http\Middleware\TrustProxies::class);

        // Defensive HTTP response headers (HSTS, X-Content-Type-Options, etc.)
        // Runs on every request, including API and health checks.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Register tenant middleware for web routes after sessions are available
        $middleware->web(append: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // Resolve tenant context for machine-to-machine API access before route binding
        $middleware->api(prepend: [
            \App\Http\Middleware\AuthenticateTenantApiToken::class,
        ], remove: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // Register middleware alias for manual usage
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant-api' => \App\Http\Middleware\AuthenticateTenantApiToken::class,
            'tenant-activity' => \App\Http\Middleware\UpdateTenantLastSeen::class,
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'trial' => \App\Http\Middleware\EnsureTenantTrialActive::class,
            'tire-hotel' => \App\Http\Middleware\RequireTireHotelAccess::class,
            'legacy-api' => \App\Http\Middleware\AddLegacyApiDeprecationHeaders::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // D.16 — user-friendly FK constraint error messages.
        //
        // Raw database exceptions for foreign-key violations ("SQLSTATE[23503]:
        // Foreign key violation: ERROR: update or delete on table ...")
        // confuse end users. The underlying cause is always the same: the
        // user tried to delete a record that other records still reference.
        // We surface a clear, actionable message instead of the DB error.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            // Check both PostgreSQL (23503) and SQLite (19) FK constraint codes.
            $sqlState = $e->errorInfo[0] ?? '';
            $pgCode = $e->errorInfo[1] ?? 0;
            $isForeignKeyViolation = $sqlState === '23000' || $sqlState === '23503' || $pgCode === 19;

            if (! $isForeignKeyViolation) {
                return null; // Let other handlers deal with it
            }

            $message = __('This record cannot be deleted because other records depend on it. Remove or reassign the dependent records first.');

            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'foreign_key_constraint',
                    'message' => $message,
                ], 409);
            }

            return back()->with('error', $message);
        });
    })->create();
