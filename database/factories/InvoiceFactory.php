<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $statuses = ['draft', 'issued', 'paid', 'cancelled'];

        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'vehicle_id' => null,
            'work_order_id' => null,
            'quote_id' => null,
            'invoice_number' => 'INV-'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => fake()->randomElement($statuses),
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => fake()->randomFloat(2, 100, 1000),
            'tax_total' => fake()->randomFloat(2, 10, 100),
            'discount_total' => 0,
            'total' => fake()->randomFloat(2, 110, 1100),
            'paid_amount' => 0,
            'notes' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }

    public function paid(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'paid_amount' => $attributes['total'],
            ];
        });
    }
}
