<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 *
 * IMPORTANTE: siempre pasar 'product_id' explícitamente al crear.
 * Si no se pasa, se crea un product nuevo (side effect).
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id'   => Order::factory(),
            'product_id' => Product::factory(),
            'qty'        => fake()->numberBetween(1, 5),
            'price'      => 100,
        ];
    }
}
