<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function buyer_can_add_product_to_wishlist(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/wishlist', ['product_id' => $product->id]);

        $response->assertCreated()
            ->assertJsonPath('item.product_id', $product->id);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function adding_same_product_twice_does_not_duplicate(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($buyer, 'sanctum')->postJson('/api/wishlist', ['product_id' => $product->id]);
        $this->actingAs($buyer, 'sanctum')->postJson('/api/wishlist', ['product_id' => $product->id]);

        $this->assertEquals(1, WishlistItem::where('user_id', $buyer->id)->count());
    }

    #[Test]
    public function buyer_can_list_their_wishlist(): void
    {
        $buyer = User::factory()->create();
        Product::factory()->count(3)->create();
        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => 1]);
        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => 2]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/wishlist');

        $response->assertOk()
            ->assertJsonPath('count', 2);
    }

    #[Test]
    public function buyer_can_remove_from_wishlist(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->create();
        WishlistItem::create(['user_id' => $buyer->id, 'product_id' => $product->id]);

        $this->actingAs($buyer, 'sanctum')
            ->deleteJson("/api/wishlist/{$product->id}")
            ->assertOk();

        $this->assertDatabaseMissing('wishlist_items', [
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function buyer_cannot_see_another_users_wishlist(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        Product::factory()->count(2)->create();
        WishlistItem::create(['user_id' => $other->id, 'product_id' => 1]);
        WishlistItem::create(['user_id' => $other->id, 'product_id' => 2]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/wishlist');

        $response->assertOk()->assertJsonPath('count', 0);
    }

    #[Test]
    public function unauthenticated_cannot_use_wishlist(): void
    {
        $this->getJson('/api/wishlist')->assertStatus(401);
        $this->postJson('/api/wishlist', ['product_id' => 1])->assertStatus(401);
    }
}
