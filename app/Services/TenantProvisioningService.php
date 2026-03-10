<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantProvisioningService
{
    public function provisionOwner(array $data, bool $dispatchRegisteredEvent = true): User
    {
        return DB::transaction(function () use ($data, $dispatchRegisteredEvent) {
            $tenant = $this->createTenant(
                companyName: $data['company_name'],
                email: $data['email'],
                plan: $data['plan'] ?? Tenant::PLAN_BASIC
            );

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);

            $this->assignAdminRole($user);
            $this->seedStarterCatalog($tenant);

            if ($dispatchRegisteredEvent) {
                DB::afterCommit(fn () => event(new Registered($user)));
            }

            return $user;
        });
    }

    public function provisionTenantForExistingUser(User $user, array $data): Tenant
    {
        if ($user->tenant_id) {
            throw new \RuntimeException('User already belongs to a tenant.');
        }

        return DB::transaction(function () use ($user, $data) {
            $tenant = $this->createTenant(
                companyName: $data['company_name'],
                email: $user->email,
                plan: $data['plan'] ?? Tenant::PLAN_BASIC
            );

            $user->forceFill([
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ])->save();

            $this->assignAdminRole($user);
            $this->seedStarterCatalog($tenant);

            return $tenant;
        });
    }

    private function createTenant(string $companyName, string $email, string $plan): Tenant
    {
        $limits = $this->planLimits($plan);
        $slug = $this->uniqueSlug($companyName);

        $tenant = Tenant::create([
            'name' => $companyName,
            'slug' => $slug,
            'subdomain' => $slug,
            'email' => $email,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(14),
            'is_active' => true,
            'plan' => $plan,
            'max_users' => $limits['max_users'],
            'max_customers' => $limits['max_customers'],
            'max_vehicles' => $limits['max_vehicles'],
            'max_work_orders' => $limits['max_work_orders'],
            'features' => $limits['features'],
            'settings' => [
                'tax_rate' => config('crm.tax_rate', 18),
                'onboarding_complete' => false,
            ],
        ]);

        \App\Models\TenantApiToken::issue($tenant, 'default');

        return $tenant;
    }

    private function assignAdminRole(User $user): void
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );

        $user->assignRole($adminRole);
    }

    private function uniqueSlug(string $companyName): string
    {
        $baseSlug = Str::slug($companyName);
        $slug = $baseSlug;
        $suffix = 1;

        while (Tenant::withoutTrashed()->where('slug', $slug)->exists() || Tenant::withoutTrashed()->where('subdomain', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function planLimits(string $plan): array
    {
        return match ($plan) {
            Tenant::PLAN_STANDARD => [
                'max_users' => 5,
                'max_customers' => 1000,
                'max_vehicles' => 3000,
                'max_work_orders' => null,
                'features' => ['dashboard_basic', 'customer_management', 'vehicle_checkin', 'appointments', 'invoicing_basic', 'tire_hotel'],
            ],
            Tenant::PLAN_CUSTOM => [
                'max_users' => 999999,
                'max_customers' => 999999,
                'max_vehicles' => 999999,
                'max_work_orders' => null,
                'features' => ['dashboard_basic', 'customer_management', 'vehicle_checkin', 'appointments', 'invoicing_basic', 'tire_hotel', 'reports'],
            ],
            default => [
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50,
                'features' => ['dashboard_basic', 'customer_management', 'vehicle_checkin', 'appointments', 'invoicing_basic'],
            ],
        };
    }

    private function seedStarterCatalog(Tenant $tenant): void
    {
        $products = [
            ['name' => 'Engine Oil 5W-40', 'sku' => 'OIL-5W40', 'price' => 45.00, 'stock_quantity' => 50],
            ['name' => 'Oil Filter', 'sku' => 'FLT-OIL-001', 'price' => 12.00, 'stock_quantity' => 100],
            ['name' => 'Air Filter', 'sku' => 'FLT-AIR-001', 'price' => 18.00, 'stock_quantity' => 80],
            ['name' => 'Brake Pads (Front)', 'sku' => 'BRK-PAD-F', 'price' => 65.00, 'stock_quantity' => 40],
        ];

        foreach ($products as $product) {
            Product::create([
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
            Service::create([
                'tenant_id' => $tenant->id,
                'name' => $service['name'],
                'price' => $service['price'],
                'is_active' => true,
                'is_template' => true,
            ]);
        }
    }
}
