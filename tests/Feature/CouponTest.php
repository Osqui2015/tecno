<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function makeBuyerWithProfile(): User
    {
        return User::factory()->create([
            'name'     => 'Test',
            'lastname' => 'User',
            'phone'    => '+54 11 5555',
            'address'  => 'Av Test 123',
            'city'     => 'CABA',
            'zip_code' => 'C1000',
        ]);
    }

    private function addToCart(User $buyer, Product $product, int $qty = 1): void
    {
        CartItem::create([
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
            'qty'        => $qty,
        ]);
    }

    #[Test]
    public function percent_coupon_is_applied_correctly(): void
    {
        config(['store.min_purchase' => 0]);

        $coupon = Coupon::factory()->create(['code' => 'VERANO25', 'type' => 'percent', 'value' => 25]);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 2); // subtotal = 2000

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'VERANO25']);

        $response->assertCreated()
            ->assertJsonPath('subtotal', '2000.00')
            ->assertJsonPath('discount', '500.00')   // 2000 * 25% = 500
            ->assertJsonPath('total', '1500.00')
            ->assertJsonPath('coupon_id', $coupon->id);
    }

    #[Test]
    public function fixed_coupon_is_applied_correctly(): void
    {
        config(['store.min_purchase' => 0]);

        Coupon::factory()->fixed(200)->create(['code' => 'FIXED200']);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 3); // subtotal = 3000

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'FIXED200']);

        $response->assertCreated()
            ->assertJsonPath('discount', '200.00')
            ->assertJsonPath('total', '2800.00');
    }

    #[Test]
    public function coupon_increments_uses_count(): void
    {
        config(['store.min_purchase' => 0]);

        $coupon = Coupon::factory()->create(['code' => 'USEME', 'value' => 10]);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 1);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'USEME'])
            ->assertCreated();

        $this->assertEquals(1, $coupon->fresh()->uses_count);
    }

    #[Test]
    public function expired_coupon_is_rejected(): void
    {
        config(['store.min_purchase' => 0]);

        Coupon::factory()->expired()->create(['code' => 'OLD']);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 1);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'OLD'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['coupon_code']);
    }

    #[Test]
    public function inactive_coupon_is_rejected(): void
    {
        Coupon::factory()->inactive()->create(['code' => 'INACTIVE']);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 1);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'INACTIVE'])
            ->assertStatus(422);
    }

    #[Test]
    public function nonexistent_coupon_is_rejected(): void
    {
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 1);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'DOESNOTEXIST'])
            ->assertStatus(422);
    }

    #[Test]
    public function coupon_with_min_subtotal_is_rejected_below_min(): void
    {
        Coupon::factory()->create(['code' => 'MIN500', 'min_subtotal' => 5000]);
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10, 'active' => true]);
        $this->addToCart($buyer, $product, 1); // subtotal = 1000 < 5000

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', ['coupon_code' => 'MIN500'])
            ->assertStatus(422);
    }

    #[Test]
    public function coupon_validate_endpoint_works(): void
    {
        Coupon::factory()->create(['code' => 'PREVIEW', 'value' => 10]);

        $response = $this->postJson('/api/coupons/validate', [
            'code'     => 'PREVIEW',
            'subtotal' => 1000,
        ]);

        $response->assertOk()
            ->assertJsonPath('discount', 100)
            ->assertJsonPath('final_subtotal', 900);
    }
}
