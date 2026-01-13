<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        }

        // Register Observers
        // Register Observers
        \App\Models\Customer::observe(\App\Observers\CustomerObserver::class);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);

        // Define Gates for Role-Based Access Control
        \Illuminate\Support\Facades\Gate::define('view-financials', function ($user) {
            return $user->can('view financials');
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
    }
}
