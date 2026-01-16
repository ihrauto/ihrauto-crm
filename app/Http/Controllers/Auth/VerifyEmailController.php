<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->redirectAfterVerification();
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->redirectAfterVerification();
    }

    /**
     * Determine where to redirect after email verification.
     */
    protected function redirectAfterVerification(): RedirectResponse
    {
        // New users should go to onboarding
        if (Route::has('subscription.onboarding')) {
            return redirect()->route('subscription.onboarding', ['verified' => 1]);
        }

        return redirect()->route('dashboard', ['verified' => 1]);
    }
}
