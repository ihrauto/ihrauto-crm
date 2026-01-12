<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterTenantOwner;
use App\Http\Controllers\Controller;
use App\Models\User;
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
     * Show the create company form for new Google users.
     */
    public function showCreateCompany(): View|RedirectResponse
    {
        if (! session('google_user')) {
            return redirect()->route('login');
        }

        return view('auth.create-company', [
            'googleUser' => session('google_user'),
        ]);
    }

    /**
     * Store new company and user from Google OAuth.
     */
    public function storeCompany(Request $request, RegisterTenantOwner $action): RedirectResponse
    {
        $googleUser = session('google_user');

        if (! $googleUser) {
            return redirect()->route('login');
        }

        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        // Use RegisterTenantOwner action but with a random secure password
        // since Google users don't need a password (they login via OAuth)
        $user = $action->handle([
            'name' => $googleUser['name'],
            'email' => $googleUser['email'],
            'password' => \Illuminate\Support\Str::random(32),
            'company_name' => $request->company_name,
        ]);

        // Mark email as verified (Google already verified it)
        $user->markEmailAsVerified();

        // Clear session data
        session()->forget('google_user');

        // Log the user in
        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }
}
