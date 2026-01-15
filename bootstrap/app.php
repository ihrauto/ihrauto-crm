<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies for Render
        $middleware->append(\App\Http\Middleware\TrustProxies::class);

        // Register tenant middleware globally for web routes
        $middleware->web(append: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // Register tenant middleware for API routes
        $middleware->api(append: [
            \App\Http\Middleware\TenantMiddleware::class,
        ]);

        // Register middleware alias for manual usage
        $middleware->alias([
            'tenant' => \App\Http\Middleware\TenantMiddleware::class,
            'tenant-activity' => \App\Http\Middleware\UpdateTenantLastSeen::class,
            'module' => \App\Http\Middleware\CheckModuleAccess::class,
            'trial' => \App\Http\Middleware\EnsureTenantTrialActive::class,
            'tire-hotel' => \App\Http\Middleware\RequireTireHotelAccess::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
