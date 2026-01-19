<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'subdomain' => fake()->unique()->slug(2),
            'domain' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'country' => 'CH',
            'plan' => fake()->randomElement(['basic', 'standard', 'custom']),
            'max_users' => 10,
            'max_customers' => 1000,
            'max_vehicles' => 5000,
            'is_active' => true,
            'is_trial' => false,
            'trial_ends_at' => null,
            'subscription_ends_at' => null,
            'features' => [],
            'settings' => [],
            'integrations' => [],
            'logo_url' => null,
            'primary_color' => '#1A53F2',
            'secondary_color' => '#F1FF30',
            'database_name' => null,
            'timezone' => 'Europe/Vienna',
            'locale' => 'en',
            'currency' => 'EUR',
            'two_factor_required' => false,
            'ip_whitelist' => null,
            'audit_logs_enabled' => true,
            'api_key' => 'tk_' . Str::random(32),
            'api_rate_limit' => 1000,
        ];
    }
}
