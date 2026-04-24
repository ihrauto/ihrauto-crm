<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\TenantUserAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin edits an existing user inside the current tenant. Role must be
 * one the caller is allowed to assign. Password is optional — admins can
 * rename / re-role without forcing a password reset.
 */
class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedRoles = app(TenantUserAccess::class)->assignableRolesFor($this->user());

        /** @var User|null $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target?->id),
            ],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
            'password' => ['nullable', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use on IHRAUTO CRM. User emails are unique across the whole platform.',
        ];
    }
}
