<?php

use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\TireHotelController;
use Illuminate\Support\Facades\Route;

// API v1 routes with rate limiting
Route::prefix('v1')->middleware(['throttle:60,1'])->group(function () {

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

// Legacy API routes (for backward compatibility) - will be deprecated
Route::middleware(['throttle:30,1'])->group(function () {
    Route::get('/customers/search', [CustomerController::class, 'search'])
        ->name('api.customers.search');

    Route::get('/tires/search-by-registration', [TireHotelController::class, 'searchByRegistration'])
        ->name('api.tires.search-by-registration');

    Route::get('/customer/{customer}/history', [CheckinController::class, 'getCustomerHistory'])
        ->name('api.customer.history');

    Route::get('/customers/{customer}', [CheckinController::class, 'getCustomerDetails'])
        ->name('api.customer.details');
});
