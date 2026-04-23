<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\TenantSwitchController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TireHotelController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [\App\Http\Controllers\HealthController::class, 'check'])->name('health.check');

Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);

Route::middleware(['auth'])->group(function () {
    Route::get('/auth/create-company', [SocialAuthController::class, 'showCreateCompany'])
        ->name('auth.create-company');
    Route::post('/auth/create-company', [SocialAuthController::class, 'storeCompany'])
        ->name('auth.create-company.store');
});

if (app()->environment('local')) {
    Route::prefix('dev')->name('dev.')->group(function () {
        Route::get('/tenant-switch', [TenantSwitchController::class, 'index'])->name('tenant-switch');
        Route::post('/tenant-switch/{tenant}', [TenantSwitchController::class, 'switch'])->name('tenant-switch.change');
        Route::post('/tenant-clear', [TenantSwitchController::class, 'clear'])->name('tenant-clear');
        Route::get('/tenant-info', [TenantSwitchController::class, 'info'])->name('tenant-info');
    });

    // Mock checkout flow for local development only.
    Route::get('/subscription/checkout/{tenant}', [\App\Http\Controllers\SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/subscription/process/{tenant}', [\App\Http\Controllers\SubscriptionController::class, 'process'])->name('subscription.process');
}

Route::middleware(['auth'])->group(function () {
    Route::get('/billing/plans', [BillingController::class, 'index'])->name('billing.pricing');
    Route::get('/subscription/onboarding', [\App\Http\Controllers\SubscriptionController::class, 'onboarding'])->name('subscription.onboarding');
    Route::post('/subscription/setup', [\App\Http\Controllers\SubscriptionController::class, 'storeSetup'])->name('subscription.setup');
    Route::post('/subscription/tour-complete', [\App\Http\Controllers\SubscriptionController::class, 'markTourComplete'])->name('subscription.tour-complete');
});

Route::view('/', 'pricing')->name('home');

Route::middleware(['auth', 'verified', 'trial', 'tenant-activity'])->group(function () {
    Route::middleware('module:access dashboard')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::middleware('module:access check-in')->group(function () {
        Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin');
        Route::post('/checkin', [CheckinController::class, 'store'])->middleware('throttle:10,1')->name('checkin.store');
        Route::get('/checkin/{checkin}', [CheckinController::class, 'show'])->name('checkin.show');
        Route::put('/checkin/{checkin}', [CheckinController::class, 'update'])->name('checkin.update');
    });

    Route::middleware(['module:access tire-hotel', 'tire-hotel'])->group(function () {
        Route::get('/tires-hotel', [TireHotelController::class, 'index'])->name('tires-hotel');
        Route::post('/tires-hotel', [TireHotelController::class, 'store'])->name('tires-hotel.store');
        Route::get('/tires-hotel/{tire}', [TireHotelController::class, 'show'])->name('tires-hotel.show');
        Route::put('/tires-hotel/{tire}', [TireHotelController::class, 'update'])->name('tires-hotel.update');
        Route::delete('/tires-hotel/{tire}', [TireHotelController::class, 'destroy'])->name('tires-hotel.destroy');
        Route::post('/tires-hotel/{tire}/generate-work-order', [TireHotelController::class, 'generateWorkOrder'])->name('tires-hotel.generate-work-order');
        Route::get('/ajax/tires/search-by-registration', [TireHotelController::class, 'searchByRegistration'])->name('tenant.ajax.tires.search-by-registration');
        Route::get('/ajax/tires/storage/check-availability', [TireHotelController::class, 'checkAvailability'])->name('tenant.ajax.tires.storage.check');
        Route::get('/ajax/tires/{tire}', [TireHotelController::class, 'apiShow'])->name('tenant.ajax.tires.show');
    });

    Route::middleware('module:access management')->group(function () {
        Route::get('/management', [ManagementController::class, 'index'])->name('management');
        Route::get('/management/export', [ManagementController::class, 'export'])->name('management.export');
        Route::get('/management/reports', [ManagementController::class, 'reports'])->name('management.reports');

        Route::middleware('permission:manage settings')->group(function () {
            Route::get('/management/settings', [ManagementController::class, 'settings'])->name('management.settings');
            Route::post('/management/settings', [ManagementController::class, 'updateSettings'])->name('management.settings.update');
            Route::get('/management/backup', [ManagementController::class, 'downloadBackup'])->name('management.backup');
        });

        Route::middleware('permission:manage users')->group(function () {
            Route::get('/management/users/create', [ManagementController::class, 'createUser'])->name('management.users.create');
            Route::post('/management/users', [ManagementController::class, 'storeUser'])->name('management.users.store');
            Route::get('/management/users/{user}/edit', [ManagementController::class, 'editUser'])->name('management.users.edit');
            Route::put('/management/users/{user}', [ManagementController::class, 'updateUser'])->name('management.users.update');
        });

        Route::delete('/management/users/{user}', [ManagementController::class, 'destroyUser'])
            ->middleware(['permission:manage users', 'permission:delete records'])
            ->name('management.users.destroy');
    });

    Route::middleware('module:access customers')->group(function () {
        Route::post('/customers/merge', [CustomerController::class, 'merge'])->name('customers.merge');
        Route::resource('customers', CustomerController::class);
        Route::prefix('ajax')->group(function () {
            Route::get('/customers/search', [CustomerController::class, 'search'])->name('tenant.ajax.customers.search');
            Route::get('/customers/{customer}', [CustomerController::class, 'apiShow'])->name('tenant.ajax.customers.show');
            Route::get('/vehicles/by-customer/{customer}', [CustomerController::class, 'vehiclesByCustomer'])->name('tenant.ajax.vehicles.by-customer');
        });
        Route::get('/ajax/customer/{customer}/history', [CustomerController::class, 'history'])->name('tenant.ajax.customer.history');
    });

    Route::middleware('module:access work-orders')->group(function () {
        Route::get('/work-orders/board', [\App\Http\Controllers\WorkOrderController::class, 'board'])->name('work-orders.board');
        Route::get('/work-orders/employee-stats', [\App\Http\Controllers\WorkOrderController::class, 'employeeStats'])->name('work-orders.employee-stats');
        Route::get('/work-orders/employee/{user}', [\App\Http\Controllers\WorkOrderController::class, 'showEmployeeStats'])->name('work-orders.employee-details');
        Route::resource('work-orders', \App\Http\Controllers\WorkOrderController::class);
        Route::post('/checkin/{checkin}/generate-wo', [\App\Http\Controllers\WorkOrderController::class, 'generate'])->name('work-orders.generate');
        Route::post('/work-orders/{workOrder}/generate-invoice', [\App\Http\Controllers\WorkOrderController::class, 'generateInvoice'])->name('work-orders.generate-invoice');
        Route::get('/work-orders/{workOrder}/details', [\App\Http\Controllers\WorkOrderController::class, 'jobDetails'])->name('work-orders.details');
        Route::post('/work-orders/{workOrder}/photos', [\App\Http\Controllers\WorkOrderPhotoController::class, 'store'])->name('work-orders.photos.store');
        Route::delete('/work-orders/{workOrder}/photos/{photo}', [\App\Http\Controllers\WorkOrderPhotoController::class, 'destroy'])
            ->middleware('permission:delete records')
            ->name('work-orders.photos.destroy');

        Route::middleware('permission:manage users')->group(function () {
            Route::resource('mechanics', \App\Http\Controllers\MechanicsController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
            // Rate limit invite resends: 5 invites per 10-minute window per user.
            // Each invite sends a transactional email, so abuse is a direct cost
            // amplifier and harassment vector against a target email address.
            Route::post('/mechanics/{mechanic}/invite', [\App\Http\Controllers\MechanicsController::class, 'invite'])
                ->middleware('throttle:5,10')
                ->name('mechanics.invite');
        });

        Route::get('/work-bays', [\App\Http\Controllers\ServiceBayController::class, 'index'])->name('work-bays.index');
        Route::post('/work-bays', [\App\Http\Controllers\ServiceBayController::class, 'store'])->name('work-bays.store');
        Route::put('/work-bays/{serviceBay}', [\App\Http\Controllers\ServiceBayController::class, 'update'])->name('work-bays.update');
        Route::delete('/work-bays/{serviceBay}', [\App\Http\Controllers\ServiceBayController::class, 'destroy'])
            ->middleware('permission:delete records')
            ->name('work-bays.destroy');
    });

    Route::middleware('module:access appointments')->group(function () {
        Route::resource('appointments', \App\Http\Controllers\AppointmentController::class);
        Route::get('/ajax/appointments/events', [\App\Http\Controllers\AppointmentController::class, 'events'])->name('appointments.events');
        Route::put('/ajax/appointments/{appointment}/reschedule', [\App\Http\Controllers\AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    });

    Route::middleware('module:access finance')->group(function () {
        Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)
            ->only(['show', 'edit', 'update', 'destroy']);
        Route::post('/invoices/{invoice}/issue', [\App\Http\Controllers\InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('/invoices/{invoice}/void', [\App\Http\Controllers\InvoiceController::class, 'void'])->name('invoices.void');
        Route::get('/finance', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
        Route::resource('payments', \App\Http\Controllers\PaymentController::class)->middleware('throttle:30,1');
    });

    Route::middleware('module:access inventory')->group(function () {
        Route::get('/ajax/products-services/search', [\App\Http\Controllers\ProductServiceController::class, 'search'])->name('api.products-services.search');
        Route::get('/products-services', [\App\Http\Controllers\ProductServiceController::class, 'index'])->name('products-services.index');
        Route::get('products/import/template', [\App\Http\Controllers\ProductController::class, 'downloadTemplate'])->name('products.import.template');
        Route::post('products/import', [\App\Http\Controllers\ProductController::class, 'import'])->name('products.import');
        Route::resource('products', \App\Http\Controllers\ProductController::class);
        Route::post('products/{product}/stock', [\App\Http\Controllers\ProductController::class, 'stockOperation'])->name('products.stock');
        Route::resource('services', \App\Http\Controllers\ServiceController::class);
        Route::post('services/{service}/toggle', [\App\Http\Controllers\ServiceController::class, 'toggle'])->name('services.toggle');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    // Throttle profile mutations — changing email is a high-value target (it
    // feeds into password reset), and bulk PATCHes are never a legitimate flow.
    Route::patch('/profile', [ProfileController::class, 'update'])
        ->middleware('throttle:10,15')
        ->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:3,15')
        ->name('profile.destroy');
});

Route::middleware(['auth', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenants', [\App\Http\Controllers\Admin\SuperAdminController::class, 'index'])->name('tenants.index');
    Route::post('/tenants/{tenant}/toggle', [\App\Http\Controllers\Admin\SuperAdminController::class, 'toggleActive'])->name('tenants.toggle');
    Route::post('/tenants/{tenant}/bonus', [\App\Http\Controllers\Admin\SuperAdminController::class, 'addBonusDays'])->name('tenants.bonus');
    Route::post('/tenants/{tenant}/billing', [\App\Http\Controllers\Admin\SuperAdminController::class, 'updateBilling'])->name('tenants.billing');
    Route::post('/tenants/{tenant}/suspend', [\App\Http\Controllers\Admin\SuperAdminController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate', [\App\Http\Controllers\Admin\SuperAdminController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/note', [\App\Http\Controllers\Admin\SuperAdminController::class, 'addNote'])->name('tenants.note');
    Route::put('/tenants/{tenant}/note/{note}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'updateNote'])->name('tenants.note.update');
    Route::delete('/tenants/{tenant}/note/{note}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'deleteNote'])->name('tenants.note.delete');
    Route::delete('/tenants/{tenant}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'destroy'])->name('tenants.destroy');
    Route::get('/tenants/{tenant}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'show'])->name('tenants.show');
});

require __DIR__.'/auth.php';
