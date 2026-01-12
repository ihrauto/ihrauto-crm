<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define Module Access Permissions
        $modulePermissions = [
            'access dashboard',
            'access check-in',
            'access tire-hotel',
            'access work-orders',
            'access appointments',
            'access finance',
            'access inventory',
            'access customers',
            'access management',
        ];

        // Define Data Scope Permissions
        $dataPermissions = [
            'view all work-orders',
            'view all appointments',
            'view all finance',
        ];

        // Define Action Permissions
        $actionPermissions = [
            'manage users',
            'manage settings',
            'delete records',
        ];

        $allPermissions = array_merge($modulePermissions, $dataPermissions, $actionPermissions);

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create Roles and Assign Permissions

        // Admin - Full access to everything
        $adminRole = Role::findOrCreate('admin');
        $adminRole->syncPermissions(Permission::all());

        // Manager - Most access, can see all data
        $managerRole = Role::findOrCreate('manager');
        $managerRole->syncPermissions([
            'access dashboard',
            'access check-in',
            'access tire-hotel',
            'access work-orders',
            'access appointments',
            'access finance',
            'access inventory',
            'access customers',
            'access management',
            'view all work-orders',
            'view all appointments',
            'view all finance',
            'manage users',
        ]);

        // Technician - Limited access, sees only own data
        $techRole = Role::findOrCreate('technician');
        $techRole->syncPermissions([
            'access dashboard',
            'access check-in',
            'access tire-hotel',
            'access work-orders',
            'access appointments',
            'access finance',
            // NO: access inventory, access customers, access management
            // NO: view all work-orders, view all appointments, view all finance (sees only own)
        ]);

        // Receptionist - Customer-facing access
        $receptionistRole = Role::findOrCreate('receptionist');
        $receptionistRole->syncPermissions([
            'access dashboard',
            'access check-in',
            'access appointments',
            'access customers',
            // NO: access tire-hotel, access work-orders, access finance, access inventory, access management
        ]);

        // Migrate existing users to the new role system (if not already assigned)
        User::all()->each(function ($user) {
            if ($user->roles->isEmpty() && $user->role) {
                $roleName = strtolower($user->role);
                if (in_array($roleName, ['admin', 'manager', 'technician', 'receptionist'])) {
                    $user->assignRole($roleName);
                }
            }
        });
    }
}
