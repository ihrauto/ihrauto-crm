<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display roles and their permissions.
     */
    public function index()
    {
        // Only show admin, manager, and technician roles for tenants
        $roles = Role::with('permissions')
            ->whereIn('name', ['admin', 'manager', 'technician'])
            ->get();
        $permissions = Permission::all()->groupBy(function ($permission) {
            // Group permissions by their first word (module)
            $parts = explode(' ', $permission->name);

            return $parts[0] ?? 'other';
        });

        return view('management.roles.index', compact('roles', 'permissions'));
    }

    /**
     * Update role permissions.
     */
    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', "Permissions updated for {$role->name} role.");
    }
}
