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

        // Seed example products and services for the new tenant
        $this->seedDemoCatalog($tenant);

        // Fire Registered event for email verification (wrapped to prevent failure)
        try {
            event(new Registered($user));
        } catch (\Exception $e) {
            // Log but don't fail registration - user can resend verification email
            \Log::warning('Failed to send verification email: ' . $e->getMessage());
        }

        return $user;
    }

    /**
     * Seed example products and services for a new tenant.
     */
    protected function seedDemoCatalog(Tenant $tenant): void
    {
        $products = [
            ['name' => 'Engine Oil 5W-40', 'sku' => 'OIL-5W40', 'price' => 45.00, 'stock_quantity' => 50],
            ['name' => 'Oil Filter', 'sku' => 'FLT-OIL-001', 'price' => 12.00, 'stock_quantity' => 100],
            ['name' => 'Air Filter', 'sku' => 'FLT-AIR-001', 'price' => 18.00, 'stock_quantity' => 80],
            ['name' => 'Brake Pads (Front)', 'sku' => 'BRK-PAD-F', 'price' => 65.00, 'stock_quantity' => 40],
        ];

        foreach ($products as $product) {
            \App\Models\Product::create([
                'tenant_id' => $tenant->id,
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => $product['price'],
                'stock_quantity' => $product['stock_quantity'],
                'is_template' => true,
            ]);
        }

        $services = [
            ['name' => 'Oil Change', 'price' => 49.00],
            ['name' => 'Tire Rotation', 'price' => 25.00],
            ['name' => 'Brake Inspection', 'price' => 35.00],
            ['name' => 'General Inspection', 'price' => 89.00],
        ];

        foreach ($services as $service) {
            \App\Models\Service::create([
                'tenant_id' => $tenant->id,
                'name' => $service['name'],
                'price' => $service['price'],
                'is_active' => true,
                'is_template' => true,
            ]);
        }
    }
}
