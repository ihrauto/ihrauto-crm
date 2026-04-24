<?php

namespace App\Http\Requests\Admin;

use App\Support\TenantUserAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin creates a new user inside the current tenant. Role must be one the
 * caller is allowed to assign — resolved dynamically via
 * TenantUserAccess::assignableRolesFor(). The password rule defers to the
 * app-wide Password::defaults() installed in AppServiceProvider.
 */
class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $allowedRoles = app(TenantUserAccess::class)->assignableRolesFor($this->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'string', Rule::in($allowedRoles)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address is already in use on IHRAUTO CRM. User emails are unique across the whole platform.',
        ];
    }
}
