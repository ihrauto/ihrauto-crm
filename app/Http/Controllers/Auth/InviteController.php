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
        $user = $this->findUserByInviteToken($token);

        if (! $user) {
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
        $user = $this->findUserByInviteToken($token);

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This invitation link is invalid or has expired.']);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Set password and clear invite token. `is_active` and `email_verified_at`
        // are protected fields set via forceFill (not mass assignment) — invite
        // acceptance is a trusted flow that legitimately activates the account.
        $user->fill([
            'password' => Hash::make($request->password),
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);
        $user->forceFill([
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        // Log the user in
        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Your account has been activated! Welcome to IHR Auto CRM.');
    }

    /**
     * Find a user by their invite token using timing-safe comparison.
     *
     * CRITICAL: direct `where('invite_token', $token)` is vulnerable to timing
     * attacks — the DB comparison short-circuits on the first differing byte,
     * leaking valid token prefixes. Instead, fetch all candidates (unexpired
     * invites are a small set) and compare each with hash_equals(), which runs
     * in constant time.
     *
     * withoutGlobalScopes() is intentional: invite acceptance happens before
     * authentication, so tenant_id() is not yet set. Tokens are high-entropy
     * secrets that act as the only authenticator.
     */
    private function findUserByInviteToken(string $token): ?User
    {
        if ($token === '' || mb_strlen($token) < 20) {
            // Reject obviously-invalid tokens without hitting the DB
            return null;
        }

        $candidates = User::withoutGlobalScopes()
            ->whereNotNull('invite_token')
            ->where('invite_expires_at', '>', now())
            ->get();

        foreach ($candidates as $candidate) {
            if (hash_equals((string) $candidate->invite_token, $token)) {
                return $candidate;
            }
        }

        return null;
    }
}
