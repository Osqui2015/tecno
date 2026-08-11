<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeBuyerWithPurchase(Product $product): User
    {
        $buyer = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status'  => Order::STATUS_CONFIRMED,
        ]);
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'qty'        => 1,
        ]);
        return $buyer;
    }

    #[Test]
    public function anyone_can_list_reviews_of_a_product(): void
    {
        $product = Product::factory()->create();
        Review::factory()->count(3)->create(['product_id' => $product->id, 'rating' => 5]);

        $response = $this->getJson("/api/products/{$product->id}/reviews");

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('avg_rating', 5);
    }

    #[Test]
    public function buyer_who_purchased_can_review(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->makeBuyerWithPurchase($product);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating'  => 5,
                'comment' => 'Excelente producto',
            ]);

        $response->assertCreated()
            ->assertJsonPath('review.rating', 5)
            ->assertJsonPath('review.is_verified_purchase', true);
    }

    #[Test]
    public function buyer_who_did_not_purchase_cannot_review(): void
    {
        $product = Product::factory()->create();
        $buyer = User::factory()->create(); // sin compra

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", [
                'rating' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['review']);
    }

    #[Test]
    public function buyer_cannot_review_twice(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->makeBuyerWithPurchase($product);
        Review::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/products/{$product->id}/reviews", ['rating' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['review']);
    }

    #[Test]
    public function reviewer_can_delete_their_own_review(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->makeBuyerWithPurchase($product);
        $review = Review::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($buyer, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    #[Test]
    public function other_users_cannot_delete_someones_review(): void
    {
        $product = Product::factory()->create();
        $owner = $this->makeBuyerWithPurchase($product);
        $other = $this->makeBuyerWithPurchase($product);
        $review = Review::factory()->create(['user_id' => $owner->id, 'product_id' => $product->id]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_delete_any_review(): void
    {
        $product = Product::factory()->create();
        $buyer = $this->makeBuyerWithPurchase($product);
        $admin = User::factory()->admin()->create();
        $review = Review::factory()->create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }
}
