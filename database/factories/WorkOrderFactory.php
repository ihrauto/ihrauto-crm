<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        $statuses = ['created', 'pending', 'in_progress', 'completed', 'cancelled'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => Vehicle::factory(),
            'technician_id' => null,
            'checkin_id' => null,
            'status' => fake()->randomElement($statuses),
            'customer_issues' => fake()->paragraph(),
            'technician_notes' => fake()->optional()->paragraph(),
            'service_tasks' => null,
            'parts_used' => null,
            'started_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
            'completed_at' => null,
        ];
    }

    public function completed(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
