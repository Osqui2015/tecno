<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'total'             => fake()->randomFloat(2, 100, 5000),
            'status'            => Order::STATUS_PENDING,
            'shipping_address'  => fake()->address(),
            'customer_name'     => fake()->firstName(),
            'customer_lastname' => fake()->lastName(),
            'customer_phone'    => '+54 11 5555-0000',
            'customer_address'  => fake()->streetAddress(),
            'customer_city'     => fake()->city(),
            'customer_zip'      => 'C1000',
            'customer_notes'    => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_CONFIRMED]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => Order::STATUS_CANCELLED]);
    }
}
