<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        // SECURITY: require the current password whenever the email is being
        // changed. Without this, any attacker with an authenticated session
        // (stolen cookie, XSS) could rewrite the email, clear verification,
        // and take over the account through "forgot password" to the new
        // address. Same pattern as ProfileController::destroy.
        if ($this->isEmailChanging()) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        return $rules;
    }

    /**
     * True when the submitted email differs from the authenticated user's
     * current email (case-insensitive).
     */
    protected function isEmailChanging(): bool
    {
        $submitted = strtolower(trim((string) $this->input('email', '')));
        $current = strtolower((string) optional($this->user())->email);

        return $submitted !== '' && $submitted !== $current;
    }
}
