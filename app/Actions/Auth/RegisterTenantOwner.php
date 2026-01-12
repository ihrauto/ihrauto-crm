<?php

namespace App\Actions\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RegisterTenantOwner
{
    /**
     * Handle tenant owner registration.
     *
     * Creates a new tenant with 14-day trial, creates the owner user,
     * assigns admin role, and fires the Registered event for email verification.
     *
     * @param  array  $data  Array containing: name, email, password, company_name
     * @return User The created user
     */
    public function handle(array $data): User
    {
        $plan = $data['plan'] ?? 'basic';

        // Define limits based on plan (should match Tenant::getPlanLimits)
        $limits = match ($plan) {
            'basic' => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
            ],
            'standard' => [
                'max_users' => 5,
                'max_customers' => 1000,
                'max_vehicles' => 3000,
                'max_work_orders' => null,
            ],
            'custom' => [
                'max_users' => 999999,
                'max_customers' => 999999,
                'max_vehicles' => 999999,
                'max_work_orders' => null,
            ],
            default => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
            ],
        };

        // Create tenant with 14-day trial
        $tenant = Tenant::create([
            'name' => $data['company_name'],
            'email' => $data['email'],
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'is_active' => true,
            'plan' => $plan,
            'max_users' => $limits['max_users'],
            'max_customers' => $limits['max_customers'],
            'max_vehicles' => $limits['max_vehicles'],
            'max_work_orders' => $limits['max_work_orders'],
        ]);

        // Create user
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => $tenant->id,
            'is_active' => true,
        ]);

        // Ensure admin role exists and assign it
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );
        $user->assignRole($adminRole);

        // Fire Registered event for email verification
        event(new Registered($user));

        return $user;
    }
}
