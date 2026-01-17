<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InviteController extends Controller
{
    /**
     * Show the password setup form.
     */
    public function showSetupForm(string $token)
    {
        $user = User::withoutGlobalScopes()
            ->where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This invitation link is invalid or has expired.']);
        }

        return view('auth.set-password', [
            'token' => $token,
            'email' => $user->email,
            'name' => $user->name,
        ]);
    }

    /**
     * Handle password setup.
     */
    public function setup(Request $request, string $token)
    {
        $user = User::withoutGlobalScopes()
            ->where('invite_token', $token)
            ->where('invite_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This invitation link is invalid or has expired.']);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Set password and clear invite token
        $user->update([
            'password' => Hash::make($request->password),
            'invite_token' => null,
            'invite_expires_at' => null,
            'email_verified_at' => now(), // Mark as verified
        ]);

        // Log the user in
        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Your account has been activated! Welcome to IHR Auto CRM.');
    }
}
