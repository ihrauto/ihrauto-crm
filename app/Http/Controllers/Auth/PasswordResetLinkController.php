<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // SECURITY (H-4): respond with a constant success banner regardless
        // of whether the email belongs to a registered user. The prior
        // behaviour distinguished RESET_LINK_SENT from INVALID_USER in the
        // UI, which is a free account-enumeration oracle — a single submit
        // per email tells the attacker if that email has an account. The
        // 3/5m route throttle slows enumeration but does not prevent it.
        //
        // Real errors (throttle, mailer failure) are recorded to the log so
        // operators can still notice them.
        $status = Password::sendResetLink($request->only('email'));

        if ($status !== Password::RESET_LINK_SENT) {
            \Illuminate\Support\Facades\Log::info('password_reset_request_suppressed', [
                'status' => $status,
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('status', __(Password::RESET_LINK_SENT));
    }
}
