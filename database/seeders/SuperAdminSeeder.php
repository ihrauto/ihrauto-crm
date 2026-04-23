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
        $email = env('SUPERADMIN_EMAIL', env('INITIAL_ADMIN_EMAIL', 'info@ihrauto.ch'));
        $name = env('SUPERADMIN_NAME', env('INITIAL_ADMIN_NAME', 'Platform Owner'));
        $password = env('SUPERADMIN_PASSWORD', env('INITIAL_ADMIN_PASSWORD'));

        // D-02: hard fail in production if the password isn't explicitly set.
        // Otherwise the fallback would ship a superadmin with password "password",
        // which grants full platform takeover to anyone who finds the login URL.
        if (app()->environment('production') && empty($password)) {
            throw new \RuntimeException(
                'SUPERADMIN_PASSWORD (or INITIAL_ADMIN_PASSWORD) must be set '
                .'in production. Refusing to seed a super-admin with a default password.'
            );
        }

        // Outside production, fall back to a well-known dev password so local
        // / CI setup keeps working without env gymnastics.
        $password = $password ?: 'password';

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

        // Create superadmin user (no tenant_id). tenant_id / is_active /
        // email_verified_at are protected fields set via forceFill.
        $superAdmin = new User;
        $superAdmin->fill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $superAdmin->forceFill([
            'tenant_id' => null,
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $superAdmin->assignRole($superAdminRole);

        $this->command->info("Superadmin created: {$email}");
    }
}
