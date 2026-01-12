<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $makes = ['Toyota', 'Honda', 'BMW', 'Mercedes', 'Audi', 'VW', 'Ford', 'Chevrolet'];
        $models = ['Corolla', 'Camry', 'Civic', 'Accord', '3 Series', 'C-Class', 'A4', 'Golf'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'license_plate' => strtoupper(fake()->unique()->bothify('?? ### ??')),
            'make' => fake()->randomElement($makes),
            'model' => fake()->randomElement($models),
            'year' => fake()->numberBetween(2010, 2024),
            'color' => fake()->safeColorName(),
            'vin' => strtoupper(fake()->unique()->bothify('?????????????????')),
            'mileage' => fake()->numberBetween(10000, 200000),
            'fuel_type' => fake()->randomElement(['gasoline', 'diesel', 'electric', 'hybrid']),
            'transmission' => fake()->randomElement(['manual', 'automatic']),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
