<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code'         => strtoupper(fake()->unique()->lexify('?????')),
            'type'         => Coupon::TYPE_PERCENT,
            'value'        => 10,
            'min_subtotal' => null,
            'max_uses'     => null,
            'uses_count'   => 0,
            'active'       => true,
        ];
    }

    public function fixed(float $value = 100): static
    {
        return $this->state(fn () => [
            'type'  => Coupon::TYPE_FIXED,
            'value' => $value,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at'  => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
