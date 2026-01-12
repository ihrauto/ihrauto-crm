<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    public function definition(): array
    {
        $serviceTypes = ['tire_change', 'oil_change', 'inspection', 'maintenance', 'repair'];
        $statuses = ['pending', 'in_progress', 'completed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'service_type' => fake()->randomElement($serviceTypes),
            'service_description' => fake()->sentence(),
            'status' => fake()->randomElement($statuses),
            'priority' => fake()->randomElement($priorities),
            'service_bay' => 'Bay '.fake()->numberBetween(1, 6),
            'checkin_time' => fake()->dateTimeBetween('-30 days', 'now'),
            'checkout_time' => null,
            'actual_cost' => fake()->optional()->randomFloat(2, 50, 500),
        ];
    }

    public function completed(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'checkout_time' => now(),
            'actual_cost' => fake()->randomFloat(2, 100, 800),
        ]);
    }

    public function pending(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'checkout_time' => null,
        ]);
    }
}
