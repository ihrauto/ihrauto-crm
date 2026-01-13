<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterTenantOwner;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, RegisterTenantOwner $action): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'in:basic,standard,custom'],
        ], [
            'email.unique' => 'This email already exists. Please use a different email or login to your existing account.',
        ]);

        $user = $action->handle([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'company_name' => $request->company_name,
            'plan' => $request->plan ?? 'basic',
        ]);

        Auth::login($user);
        session(['tenant_id' => $user->tenant_id]);

        // Track tenant registration event
        app(\App\Services\EventTracker::class)->track('tenant_registered', $user->tenant_id, $user->id);

        // Redirect to onboarding if available, otherwise dashboard
        if (Route::has('subscription.onboarding')) {
            return redirect()->route('subscription.onboarding');
        }

        return redirect()->route('dashboard');
    }
}
