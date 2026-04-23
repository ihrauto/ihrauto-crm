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
        }

        // S-07: resolve auto-login eligibility ONCE at boot instead of probing
        // the filesystem on every request. That kills a subtle timing side
        // channel (file_exists() latency deltas on shared filesystems) and
        // makes the invariant "auto-login can never run outside local" a
        // compile-time style guarantee rather than a middleware convention.
        config()->set('app.auto_login_verified', \App\Support\AutoLoginGuard::resolve());

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->input('email', '').'|'.$request->ip());
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
            return $user->can('access finance');
        });

        \Illuminate\Support\Facades\Gate::define('delete-records', function ($user) {
            return $user->can('delete records');
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
    }
}
