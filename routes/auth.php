<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Rate-limited to 3 requests per 5-minute window per IP. Password reset link
    // requests are expensive (send email, write DB, user confusion if spammed).
    // The previous 5/min limit permitted 300 emails per hour per IP; too generous.
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email')
        ->middleware('throttle:3,5');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    // Same rate limit as forgot-password. Brute-forcing the token would require
    // ~2^256 attempts (Laravel tokens are signed hashes), but a slow limit
    // protects against distributed token-guessing at scale.
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store')
        ->middleware('throttle:3,5');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

// Invite/Password Setup Routes (public, token-based)
Route::get('invite/{token}', [\App\Http\Controllers\Auth\InviteController::class, 'showSetupForm'])
    ->name('invite.setup');
Route::post('invite/{token}', [\App\Http\Controllers\Auth\InviteController::class, 'setup'])
    ->name('invite.setup.store');
