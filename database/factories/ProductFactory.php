<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name'        => ucwords($name),
            'slug'        => Str::slug($name) . '-' . Str::random(4),
            'description' => fake()->sentence(),
            'price'       => fake()->randomFloat(2, 100, 10000),
            'list_price'  => null,
            'cash_price'  => null,
            'stock'       => fake()->numberBetween(0, 100),
            'image'       => null,
            'brand'       => fake()->company(),
            'source_url'  => null,
            // Tecno-Rexs vende productos Daz → factory los genera como Daz por default.
            'external_id' => 'DAZ-' . strtoupper(Str::random(8)),
            'category_id' => Category::factory(),
            'active'      => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }

    public function fromDaz(): static
    {
        return $this->state(fn () => [
            'external_id' => 'DAZ-' . strtoupper(Str::random(8)),
            'origin'      => 'daz',
        ]);
    }

    public function fromTuc(): static
    {
        return $this->state(fn () => [
            'external_id' => 'TUC-' . strtoupper(Str::random(8)),
            'origin'      => 'tuc',
        ]);
    }
}
