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
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'subdomain' => fake()->unique()->slug(2),
            'domain' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'country' => 'CH',
            'plan' => fake()->randomElement(['basic', 'standard', 'custom']),
            'max_users' => fn (array $attributes) => Tenant::planDefinition($attributes['plan'])['limits']['max_users'],
            'max_customers' => fn (array $attributes) => Tenant::planDefinition($attributes['plan'])['limits']['max_customers'],
            'max_vehicles' => fn (array $attributes) => Tenant::planDefinition($attributes['plan'])['limits']['max_vehicles'],
            'max_work_orders' => fn (array $attributes) => Tenant::planDefinition($attributes['plan'])['limits']['max_work_orders'],
            'is_active' => true,
            'is_trial' => false,
            'trial_ends_at' => null,
            'subscription_ends_at' => null,
            'features' => fn (array $attributes) => Tenant::planDefinition($attributes['plan'])['features'],
            'settings' => [],
            'integrations' => [],
            'logo_url' => null,
            'primary_color' => '#1A53F2',
            'secondary_color' => '#F1FF30',
            'database_name' => null,
            'timezone' => 'Europe/Vienna',
            'locale' => 'en',
            'currency' => 'EUR',
            // `two_factor_required` intentionally not set here (H-1): the
            // column is not fillable because 2FA is not implemented. The
            // table default (false) applies.
            'ip_whitelist' => null,
            'audit_logs_enabled' => true,
            'api_key' => null,
            'api_rate_limit' => 1000,
        ];
    }
}
