<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterTenantOwner;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google OAuth.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle callback from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', 'Failed to authenticate with Google. Please try again.');
        }

        // Check if user already exists
        $existingUser = User::withoutGlobalScopes()
            ->where('email', $googleUser->getEmail())
            ->first();

        if ($existingUser) {
            // Log in existing user
            Auth::login($existingUser, remember: true);

            return redirect()->intended(route('dashboard'));
        }

        // New user - store Google data in session and redirect to company creation
        session([
            'google_user' => [
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ],
        ]);

        return redirect()->route('auth.create-company');
    }

    /**
     * Show the create company form for new users without a tenant.
     * Works for both Google OAuth users and regular authenticated users.
     */
    public function showCreateCompany(): View|RedirectResponse
    {
        // Google OAuth flow - has google_user in session
        if (session('google_user')) {
            return view('auth.create-company', [
                'googleUser' => session('google_user'),
            ]);
        }

        // Regular authenticated user without a tenant
        if (Auth::check()) {
            return view('auth.create-company', [
                'googleUser' => null,
                'user' => Auth::user(),
            ]);
        }

        // Not authenticated at all - redirect to login
        return redirect()->route('login');
    }

    /**
     * Store new company for both Google OAuth and regular authenticated users.
     */
    public function storeCompany(Request $request, RegisterTenantOwner $action, TenantProvisioningService $tenantProvisioningService): RedirectResponse
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        $googleUser = session('google_user');

        if ($googleUser) {
            // Google OAuth flow - create new user and tenant
            $user = $tenantProvisioningService->provisionOwner([
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'password' => \Illuminate\Support\Str::random(32),
                'company_name' => $request->company_name,
            ], dispatchRegisteredEvent: false);

            // Mark email as verified (Google already verified it)
            $user->markEmailAsVerified();

            // Clear session data
            session()->forget('google_user');

            // Log the user in
            Auth::login($user, remember: true);
        } elseif (Auth::check()) {
            // Regular authenticated user - create tenant and associate
            $user = Auth::user();

            $tenantProvisioningService->provisionTenantForExistingUser($user, [
                'company_name' => $request->company_name,
                'plan' => \App\Models\Tenant::PLAN_BASIC,
            ]);
        } else {
            return redirect()->route('login');
        }

        return redirect()->route('dashboard');
    }
}
