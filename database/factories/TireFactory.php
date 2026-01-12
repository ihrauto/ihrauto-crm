<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Tire;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

class TireFactory extends Factory
{
    protected $model = Tire::class;

    public function definition(): array
    {
        $brands = ['Michelin', 'Bridgestone', 'Goodyear', 'Continental', 'Pirelli', 'Dunlop'];
        $seasons = ['summer', 'winter', 'all_season'];
        $conditions = ['excellent', 'good', 'fair', 'poor'];
        $statuses = ['stored', 'ready_pickup', 'picked_up', 'maintenance'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'brand' => fake()->randomElement($brands),
            'model' => fake()->word().' Sport',
            'size' => fake()->randomElement(['205/55R16', '225/45R17', '235/40R18', '195/65R15']),
            'season' => fake()->randomElement($seasons),
            'quantity' => fake()->randomElement([2, 4]),
            'tread_depth' => fake()->randomFloat(1, 2, 8),
            'condition' => fake()->randomElement($conditions),
            'status' => fake()->randomElement($statuses),
            'storage_location' => fake()->randomElement(['A', 'B', 'C', 'D']).fake()->numberBetween(1, 50),
            'storage_fee' => fake()->randomFloat(2, 30, 60),
            'storage_date' => fake()->dateTimeBetween('-90 days', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function stored(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'stored',
        ]);
    }
}
