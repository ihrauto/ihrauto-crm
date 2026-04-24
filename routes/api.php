<?php

use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\TireHotelController;
use Illuminate\Support\Facades\Route;

// API v1 routes with rate limiting
Route::prefix('v1')->middleware(['throttle:tenant-api'])->group(function () {

    // Customer endpoints
    Route::prefix('customers')->group(function () {
        Route::get('search', [CustomerController::class, 'search'])
            ->name('api.v1.customers.search');
        Route::get('{customer}', [CustomerController::class, 'show'])
            ->name('api.v1.customers.show');
        Route::get('{customer}/vehicles', [CustomerController::class, 'vehicles'])
            ->name('api.v1.customers.vehicles');
        Route::get('{customer}/history', [CheckinController::class, 'getCustomerHistory'])
            ->name('api.v1.customers.history');
    });

    // Checkin endpoints
    Route::prefix('checkins')->group(function () {
        Route::get('active', [CheckinController::class, 'getActiveCheckins'])
            ->name('api.v1.checkins.active');
        Route::get('statistics', [CheckinController::class, 'getStatistics'])
            ->name('api.v1.checkins.statistics');
    });

    // Tire endpoints
    Route::prefix('tires')->group(function () {
        Route::get('search-by-registration', [TireHotelController::class, 'searchByRegistration'])
            ->name('api.v1.tires.search-by-registration');
    });
});

// =============================================================================
// LEGACY API — scheduled for removal 2026-06-30.
// =============================================================================
// These paths existed before the /api/v1/* versioning scheme. Every request
// that lands here is tagged with Deprecation / Sunset / Link response headers
// by `AddLegacyApiDeprecationHeaders`, matching RFC 8594.
//
// Removal procedure (on 2026-06-30, or once the Sunset header has been live
// in production for at least 90 days with zero usage in the `legacy_api_call`
// log channel — whichever is later):
//
//   1. Delete this entire block.
//   2. Delete app/Http/Middleware/AddLegacyApiDeprecationHeaders.php and its
//      `legacy-api` alias in bootstrap/app.php.
//   3. Remove the `legacy_api_call` log channel tailing from ops dashboards.
//
// Until removal, callers MUST still present a bearer tenant API token; the
// `api` middleware group prepends `AuthenticateTenantApiToken` for every
// route in this file, legacy included.
// =============================================================================
Route::middleware(['throttle:tenant-api', 'legacy-api'])->group(function () {
    Route::get('/customers/search', [CustomerController::class, 'search'])
        ->name('api.customers.search');

    Route::get('/tires/search-by-registration', [TireHotelController::class, 'searchByRegistration'])
        ->name('api.tires.search-by-registration');

    Route::get('/customer/{customer}/history', [CheckinController::class, 'getCustomerHistory'])
        ->name('api.customer.history');

    Route::get('/customers/{customer}', [CheckinController::class, 'getCustomerDetails'])
        ->name('api.customer.details');
});
