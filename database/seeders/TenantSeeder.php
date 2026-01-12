<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test tenants with the approved plan structure
        // BASIC: €49/month - Solo mechanics, small workshops
        // STANDARD: €149/month - Growing garages with multiple employees
        // CUSTOM: Contact sales - Large workshops, franchises
        $tenants = [
            [
                'name' => 'Quick Fix Motors',
                'slug' => 'quick-fix-motors',
                'subdomain' => 'quickfix',
                'email' => 'manager@quickfix.com',
                'phone' => '+383 44 987 654',
                'address' => 'Rr. UCK 456',
                'city' => 'Prizren',
                'country' => 'Kosovo',
                'plan' => 'basic',
                'max_users' => 1,
                'max_customers' => 100,
                'max_vehicles' => 200,
                'max_work_orders' => 50, // Monthly limit
                'is_active' => true,
                'is_trial' => true,
                'trial_ends_at' => Carbon::now()->addDays(14),
                'features' => [
                    'dashboard_basic',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_limited',
                    'appointments',
                    'invoicing_basic',
                ],
                'primary_color' => '#2563EB',
                'secondary_color' => '#10B981',
            ],
            [
                'name' => 'AutoService Pro',
                'slug' => 'autoservice-pro',
                'subdomain' => 'autoservice',
                'email' => 'admin@autoservice.com',
                'phone' => '+383 44 123 456',
                'address' => 'Rr. Dëshmorët e Kombit 123',
                'city' => 'Pristina',
                'country' => 'Kosovo',
                'plan' => 'standard',
                'max_users' => 5,
                'max_customers' => 1000,
                'max_vehicles' => 3000,
                'max_work_orders' => null, // Unlimited
                'is_active' => true,
                'is_trial' => true,
                'trial_ends_at' => Carbon::now()->addDays(14),
                'features' => [
                    'dashboard_full',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_unlimited',
                    'appointments',
                    'invoicing_custom',
                    'tire_hotel',
                    'multi_user',
                    'staff_management',
                    'advanced_reports',
                    'email_support',
                ],
                'primary_color' => '#1A53F2',
                'secondary_color' => '#F1FF30',
            ],
            [
                'name' => 'Elite Auto Network',
                'slug' => 'elite-auto-network',
                'subdomain' => 'elite',
                'email' => 'enterprise@eliteauto.com',
                'phone' => '+383 44 555 777',
                'address' => 'Rr. Nëna Tereze 789',
                'city' => 'Peja',
                'country' => 'Kosovo',
                'plan' => 'custom',
                'max_users' => 999999, // Unlimited
                'max_customers' => 999999, // Unlimited
                'max_vehicles' => 999999, // Unlimited
                'max_work_orders' => null, // Unlimited
                'is_active' => true,
                'is_trial' => false, // Custom plans don't have trial enforcement
                'subscription_ends_at' => Carbon::now()->addYear(),
                'features' => [
                    'dashboard_full',
                    'dashboard_custom',
                    'customer_management',
                    'vehicle_checkin',
                    'work_orders_unlimited',
                    'appointments',
                    'invoicing_whitelabel',
                    'tire_hotel',
                    'multi_user_unlimited',
                    'staff_management',
                    'advanced_reports',
                    'api_access',
                    'dedicated_support',
                    'custom_branding',
                    'custom_integrations',
                    'data_migration',
                    'on_premise_option',
                ],
                'primary_color' => '#DC2626',
                'secondary_color' => '#FBBF24',
            ],
        ];

        foreach ($tenants as $tenantData) {
            $tenant = Tenant::create($tenantData);

            // Create admin user for each tenant
            $adminUser = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Admin User',
                'email' => 'admin@'.$tenant->subdomain.'.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Create additional users for standard and custom plans
            if (in_array($tenant->plan, ['standard', 'custom'])) {
                User::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Manager User',
                    'email' => 'manager@'.$tenant->subdomain.'.com',
                    'password' => Hash::make('password'),
                    'role' => 'manager',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                User::create([
                    'tenant_id' => $tenant->id,
                    'name' => 'Technician User',
                    'email' => 'tech@'.$tenant->subdomain.'.com',
                    'password' => Hash::make('password'),
                    'role' => 'technician',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
            }

            // Sample data creation removed for clean install
        }
    }

    private function generateLicensePlate(): string
    {
        $letters = ['PR', 'PE', 'PZ', 'MI', 'GJ', 'FE', 'DJ'];
        $region = $letters[array_rand($letters)];
        $numbers = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
        $suffix = chr(rand(65, 90)).chr(rand(65, 90));

        return "{$region} {$numbers} {$suffix}";
    }

    private function getRandomMake(): string
    {
        $makes = ['BMW', 'Mercedes', 'Audi', 'Volkswagen', 'Toyota', 'Honda', 'Ford', 'Opel', 'Renault', 'Peugeot'];

        return $makes[array_rand($makes)];
    }

    private function getRandomModel(): string
    {
        $models = ['320i', 'C-Class', 'A4', 'Golf', 'Camry', 'Civic', 'Focus', 'Astra', 'Clio', '308'];

        return $models[array_rand($models)];
    }

    private function getRandomColor(): string
    {
        $colors = ['Black', 'White', 'Silver', 'Gray', 'Blue', 'Red', 'Green', 'Yellow'];

        return $colors[array_rand($colors)];
    }

    private function getRandomFuelType(): string
    {
        $types = ['petrol', 'diesel', 'hybrid', 'electric'];

        return $types[array_rand($types)];
    }

    private function getRandomServiceType(): string
    {
        $types = ['oil_change', 'tire_change', 'brake_service', 'engine_repair', 'transmission_service', 'general_maintenance'];

        return $types[array_rand($types)];
    }

    private function getRandomPriority(): string
    {
        $priorities = ['low', 'medium', 'high', 'urgent'];

        return $priorities[array_rand($priorities)];
    }

    private function getRandomStatus(): string
    {
        $statuses = ['pending', 'in_progress', 'completed'];

        return $statuses[array_rand($statuses)];
    }

    private function getRandomTireBrand(): string
    {
        $brands = ['Michelin', 'Bridgestone', 'Continental', 'Goodyear', 'Pirelli', 'Dunlop', 'Hankook'];

        return $brands[array_rand($brands)];
    }

    private function getRandomTireSize(): string
    {
        $sizes = ['205/55R16', '225/45R17', '235/35R18', '195/65R15', '215/60R16', '245/40R18'];

        return $sizes[array_rand($sizes)];
    }

    private function getRandomSeason(): string
    {
        $seasons = ['winter', 'summer', 'all_season'];

        return $seasons[array_rand($seasons)];
    }

    private function getRandomCondition(): string
    {
        $conditions = ['excellent', 'good', 'fair', 'poor'];

        return $conditions[array_rand($conditions)];
    }
}
