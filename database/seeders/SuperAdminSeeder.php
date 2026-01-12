<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates the super-admin role and initial superadmin user.
     */
    public function run(): void
    {
        // Create super-admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web']
        );

        // Get superadmin credentials from env or use defaults
        $email = env('SUPERADMIN_EMAIL', 'admin@ihrauto.com');
        $name = env('SUPERADMIN_NAME', 'Super Admin');
        $password = env('SUPERADMIN_PASSWORD', 'password');

        // Check if superadmin already exists
        $existingAdmin = User::withoutGlobalScopes()
            ->where('email', $email)
            ->first();

        if ($existingAdmin) {
            // Ensure they have the role
            if (! $existingAdmin->hasRole('super-admin')) {
                $existingAdmin->assignRole($superAdminRole);
            }
            $this->command->info("Superadmin already exists: {$email}");

            return;
        }

        // Create superadmin user (no tenant_id)
        $superAdmin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'tenant_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $superAdmin->assignRole($superAdminRole);

        $this->command->info("Superadmin created: {$email}");
    }
}
