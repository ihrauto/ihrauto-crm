<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantUserRequest;
use App\Http\Requests\Admin\UpdateTenantUserRequest;
use App\Models\User;
use App\Support\TenantUserAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * Tenant-level user management. Routes in `routes/web.php` guard
 * creation / edit / deletion behind `permission:manage users` and (for
 * delete) `permission:delete records`. TenantUserAccess enforces
 * role-transition rules (managers may not create admins, last admin
 * cannot be demoted, etc).
 */
class UserController extends Controller
{
    public function __construct(
        private readonly TenantUserAccess $tenantUserAccess,
    ) {}

    public function create()
    {
        $roles = $this->tenantUserAccess->assignableRolesFor(auth()->user());

        return view('management.users.create', compact('roles'));
    }

    public function store(StoreTenantUserRequest $request)
    {
        $validated = $request->validated();

        $user = new User;
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->password = Hash::make($validated['password']);
        $user->role = $validated['role'];

        if (auth()->check() && tenant_id()) {
            $user->tenant_id = tenant_id();
        }

        $user->save();
        $user->assignRole($validated['role']);

        return redirect()->route('management')->with('success', 'New user account created successfully.');
    }

    public function edit(User $user)
    {
        $this->tenantUserAccess->ensureCanManageUser(auth()->user(), $user);

        $roles = $this->tenantUserAccess->assignableRolesFor(auth()->user());

        return view('management.users.edit', compact('user', 'roles'));
    }

    public function update(UpdateTenantUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $this->tenantUserAccess->ensureCanTransitionUserRole($request->user(), $user, $validated['role']);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('management')->with('success', 'User account updated successfully.');
    }

    public function destroy(User $user)
    {
        Gate::authorize('delete-records');
        $this->tenantUserAccess->ensureCanDeleteUser(auth()->user(), $user);

        $user->delete();

        return back()->with('success', 'User account deleted successfully.');
    }
}
