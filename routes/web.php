<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Dev\TenantSwitchController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TireHotelController;
use Illuminate\Support\Facades\Route;

// Health check endpoint (public, no auth)
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'check'])->name('health.check');

// Google OAuth routes (public)
Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback']);
Route::get('/auth/create-company', [SocialAuthController::class, 'showCreateCompany'])->name('auth.create-company');
Route::post('/auth/create-company', [SocialAuthController::class, 'storeCompany'])->name('auth.create-company.store');

// Development routes (only available in local environment)
if (app()->environment('local')) {
    Route::prefix('dev')->name('dev.')->group(function () {
        Route::get('/tenant-switch', [TenantSwitchController::class, 'index'])->name('tenant-switch');
        Route::post('/tenant-switch/{tenant}', [TenantSwitchController::class, 'switch'])->name('tenant-switch.change');
        Route::post('/tenant-clear', [TenantSwitchController::class, 'clear'])->name('tenant-clear');
        Route::get('/tenant-info', [TenantSwitchController::class, 'info'])->name('tenant-info');
    });

    // mocked subscription flow (kept in local check for safety)
    Route::get('/subscription/checkout/{tenant}', [\App\Http\Controllers\SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::post('/subscription/process/{tenant}', [\App\Http\Controllers\SubscriptionController::class, 'process'])->name('subscription.process');
    Route::get('/subscription/onboarding', [\App\Http\Controllers\SubscriptionController::class, 'onboarding'])->name('subscription.onboarding');
    Route::post('/subscription/setup', [\App\Http\Controllers\SubscriptionController::class, 'storeSetup'])->name('subscription.setup');
    Route::post('/subscription/tour-complete', [\App\Http\Controllers\SubscriptionController::class, 'markTourComplete'])->name('subscription.tour-complete');
}

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

// Protected CRM routes
Route::middleware(['auth', 'verified', 'trial', 'tenant-activity'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Check-in routes
    Route::get('/checkin', [CheckinController::class, 'index'])->name('checkin');
    Route::post('/checkin', [CheckinController::class, 'store'])->name('checkin.store');
    Route::get('/checkin/{checkin}', [CheckinController::class, 'show'])->name('checkin.show');
    Route::put('/checkin/{checkin}', [CheckinController::class, 'update'])->name('checkin.update');

    // Tires Hotel routes (STANDARD and CUSTOM plans only)
    Route::middleware(['tire-hotel'])->group(function () {
        Route::get('/tires-hotel', [TireHotelController::class, 'index'])->name('tires-hotel');
        Route::post('/tires-hotel', [TireHotelController::class, 'store'])->name('tires-hotel.store');
        Route::get('/tires-hotel/{tire}', [TireHotelController::class, 'show'])->name('tires-hotel.show');
        Route::put('/tires-hotel/{tire}', [TireHotelController::class, 'update'])->name('tires-hotel.update');
        Route::delete('/tires-hotel/{tire}', [TireHotelController::class, 'destroy'])->name('tires-hotel.destroy');
        Route::post('/tires-hotel/{tire}/generate-work-order', [TireHotelController::class, 'generateWorkOrder'])->name('tires-hotel.generate-work-order');
    });

    // Management
    Route::get('/management', [ManagementController::class, 'index'])->name('management');
    Route::get('/management/export', [ManagementController::class, 'export'])->name('management.export');
    Route::get('/management/notifications', [ManagementController::class, 'notifications'])->name('management.notifications');
    Route::get('/management/pricing', [ManagementController::class, 'pricing'])->name('management.pricing');
    Route::get('/management/reports', [ManagementController::class, 'reports'])->name('management.reports');
    Route::get('/management/analytics', [ManagementController::class, 'analytics'])->name('management.analytics');
    Route::get('/management/audit', [ManagementController::class, 'audit'])->name('management.audit');
    Route::get('/management/settings', [ManagementController::class, 'settings'])->name('management.settings');
    Route::post('/management/settings', [ManagementController::class, 'updateSettings'])->name('management.settings.update');
    Route::get('/management/users/create', [ManagementController::class, 'createUser'])->name('management.users.create');
    Route::post('/management/users', [ManagementController::class, 'storeUser'])->name('management.users.store');
    Route::get('/management/users/{user}/edit', [ManagementController::class, 'editUser'])->name('management.users.edit');
    Route::put('/management/users/{user}', [ManagementController::class, 'updateUser'])->name('management.users.update');
    Route::delete('/management/users/{user}', [ManagementController::class, 'destroyUser'])->name('management.users.destroy');
    Route::get('/management/backup', [ManagementController::class, 'downloadBackup'])->name('management.backup');
    Route::get('/management/roles', [\App\Http\Controllers\RoleController::class, 'index'])->name('management.roles.index');
    Route::put('/management/roles/{role}', [\App\Http\Controllers\RoleController::class, 'update'])->name('management.roles.update');

    // Customer management routes
    Route::resource('customers', CustomerController::class);

    // API routes for AJAX requests
    Route::prefix('api')->group(function () {
        Route::get('/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');
        Route::get('/customers/{customer}', [CustomerController::class, 'apiShow'])->name('api.customers.show');
        Route::get('/vehicles/by-customer/{customer}', function ($customer) {
            return \App\Models\Vehicle::where('customer_id', $customer)->get();
        })->name('api.vehicles.by-customer');
        Route::get('/tires/search-by-registration', [TireHotelController::class, 'searchByRegistration'])->name('api.tires.search-by-registration');
        Route::get('/tires/storage/check-availability', [TireHotelController::class, 'checkAvailability'])->name('api.tires.storage.check');
        Route::get('/tires/{tire}', [TireHotelController::class, 'apiShow'])->name('api.tires.show');
    });

    // Customer history API endpoint
    Route::get('/api/customer/{customer}/history', [CustomerController::class, 'history'])->name('api.customer.history');

    // Work Order routes
    Route::get('/work-orders/board', [\App\Http\Controllers\WorkOrderController::class, 'board'])->name('work-orders.board');
    Route::get('/work-orders/employee-stats', [\App\Http\Controllers\WorkOrderController::class, 'employeeStats'])->name('work-orders.employee-stats');
    Route::get('/work-orders/employee/{user}', [\App\Http\Controllers\WorkOrderController::class, 'showEmployeeStats'])->name('work-orders.employee-details');
    Route::resource('work-orders', \App\Http\Controllers\WorkOrderController::class);
    Route::post('/checkin/{checkin}/generate-wo', [\App\Http\Controllers\WorkOrderController::class, 'generate'])->name('work-orders.generate');
    Route::post('/work-orders/{workOrder}/generate-invoice', [\App\Http\Controllers\WorkOrderController::class, 'generateInvoice'])->name('work-orders.generate-invoice');

    // Appointment routes
    Route::resource('appointments', \App\Http\Controllers\AppointmentController::class);

    // Billing & Finance Routes
    Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)
        ->only(['show', 'edit', 'update', 'destroy']);
    Route::post('/invoices/{invoice}/issue', [\App\Http\Controllers\InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('/invoices/{invoice}/void', [\App\Http\Controllers\InvoiceController::class, 'void'])->name('invoices.void');

    Route::get('/finance', [\App\Http\Controllers\FinanceController::class, 'index'])->name('finance.index');
    Route::resource('payments', \App\Http\Controllers\PaymentController::class)->middleware('throttle:30,1');

    // Products & Services (Inventory)
    Route::get('/api/products-services/search', [\App\Http\Controllers\ProductServiceController::class, 'search'])->name('api.products-services.search');
    Route::get('/products-services', [\App\Http\Controllers\ProductServiceController::class, 'index'])->name('products-services.index');
    Route::resource('products', \App\Http\Controllers\ProductController::class);
    Route::post('products/{product}/stock', [\App\Http\Controllers\ProductController::class, 'stockOperation'])->name('products.stock');
    Route::resource('services', \App\Http\Controllers\ServiceController::class);
    Route::post('services/{service}/toggle', [\App\Http\Controllers\ServiceController::class, 'toggle'])->name('services.toggle');

    // Profile routes (from Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Superadmin routes
Route::middleware(['auth', 'role:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenants', [\App\Http\Controllers\Admin\SuperAdminController::class, 'index'])->name('tenants.index');
    Route::post('/tenants/{tenant}/toggle', [\App\Http\Controllers\Admin\SuperAdminController::class, 'toggleActive'])->name('tenants.toggle');
    Route::post('/tenants/{tenant}/bonus', [\App\Http\Controllers\Admin\SuperAdminController::class, 'addBonusDays'])->name('tenants.bonus');
    Route::post('/tenants/{tenant}/suspend', [\App\Http\Controllers\Admin\SuperAdminController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate', [\App\Http\Controllers\Admin\SuperAdminController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/note', [\App\Http\Controllers\Admin\SuperAdminController::class, 'addNote'])->name('tenants.note');
    Route::put('/tenants/{tenant}/note/{note}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'updateNote'])->name('tenants.note.update');
    Route::delete('/tenants/{tenant}/note/{note}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'deleteNote'])->name('tenants.note.delete');
    Route::get('/tenants/{tenant}', [\App\Http\Controllers\Admin\SuperAdminController::class, 'show'])->name('tenants.show');
});

require __DIR__ . '/auth.php';
