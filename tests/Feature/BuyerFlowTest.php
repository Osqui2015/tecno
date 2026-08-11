<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BuyerFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeBuyerWithProfile(): User
    {
        return User::factory()->create([
            'name'     => 'Juan',
            'lastname' => 'Pérez',
            'phone'    => '+54 11 5555-1234',
            'address'  => 'Av. Corrientes 1234',
            'city'     => 'CABA',
            'zip_code' => 'C1043',
        ]);
    }

    // ============================================================
    //  PROFILE
    // ============================================================

    #[Test]
    public function buyer_can_view_their_profile(): void
    {
        $buyer = $this->makeBuyerWithProfile();

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/me/profile');

        $response->assertOk()
            ->assertJsonPath('user.email', $buyer->email)
            ->assertJsonPath('user.lastname', 'Pérez')
            ->assertJsonPath('profile_complete', true);
    }

    #[Test]
    public function buyer_can_update_their_profile(): void
    {
        $buyer = $this->makeBuyerWithProfile();

        $response = $this->actingAs($buyer, 'sanctum')
            ->patchJson('/api/me/profile', [
                'phone' => '+54 11 9999-0000',
                'city'  => 'La Plata',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.phone', '+54 11 9999-0000')
            ->assertJsonPath('user.city', 'La Plata');
    }

    #[Test]
    public function buyer_cannot_escalate_role_via_profile_update(): void
    {
        $buyer = $this->makeBuyerWithProfile();

        $response = $this->actingAs($buyer, 'sanctum')
            ->patchJson('/api/me/profile', [
                'role' => 'admin',
            ]);

        $response->assertOk();
        $this->assertEquals(User::ROLE_COMPRADOR, $buyer->fresh()->role);
    }

    // ============================================================
    //  CART
    // ============================================================

    #[Test]
    public function buyer_can_add_product_to_cart(): void
    {
        $buyer  = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['stock' => 10, 'price' => 100]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/cart/items', [
                'product_id' => $product->id,
                'qty'        => 2,
            ]);

        $response->assertCreated()
            ->assertJsonPath('item.qty', 2);

        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
            'qty'        => 2,
        ]);
    }

    #[Test]
    public function adding_same_product_increments_qty_not_duplicates(): void
    {
        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['stock' => 10]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'qty' => 2]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'qty' => 3]);

        $this->assertEquals(1, CartItem::where('user_id', $buyer->id)->count());
        $this->assertEquals(5, CartItem::where('user_id', $buyer->id)->first()->qty);
    }

    #[Test]
    public function buyer_cannot_add_more_than_stock(): void
    {
        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/cart/items', ['product_id' => $product->id, 'qty' => 10]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['qty']);
    }

    #[Test]
    public function buyer_can_view_cart_with_total(): void
    {
        $buyer    = $this->makeBuyerWithProfile();
        $product1 = Product::factory()->create(['price' => 100, 'stock' => 5]);
        $product2 = Product::factory()->create(['price' => 50,  'stock' => 5]);

        $this->actingAs($buyer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $product1->id, 'qty' => 2]);
        $this->actingAs($buyer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $product2->id, 'qty' => 3]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/cart');

        // 2*100 + 3*50 = 350
        $response->assertOk()
            ->assertJsonPath('total', '350.00')
            ->assertJsonPath('items_count', 5);
    }

    #[Test]
    public function buyer_can_update_qty_of_cart_item(): void
    {
        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['stock' => 10]);
        $item    = CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->patchJson("/api/cart/items/{$item->id}", ['qty' => 7]);

        $response->assertOk()
            ->assertJsonPath('item.qty', 7);
    }

    #[Test]
    public function buyer_can_remove_cart_item(): void
    {
        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['stock' => 10]);
        $item    = CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $this->actingAs($buyer, 'sanctum')
            ->deleteJson("/api/cart/items/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    #[Test]
    public function buyer_can_clear_cart(): void
    {
        $buyer = $this->makeBuyerWithProfile();
        $p1 = Product::factory()->create(['stock' => 5]);
        $p2 = Product::factory()->create(['stock' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $p1->id, 'qty' => 1]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $p2->id, 'qty' => 1]);

        $response = $this->actingAs($buyer, 'sanctum')->deleteJson('/api/cart');

        $response->assertOk()->assertJsonPath('removed', 2);
        $this->assertEquals(0, CartItem::where('user_id', $buyer->id)->count());
    }

    // ============================================================
    //  ORDERS
    // ============================================================

    #[Test]
    public function buyer_can_create_order_from_cart_and_cart_is_emptied(): void
    {
        // Para este test desactivamos el mínimo de compra
        config(['store.min_purchase' => 0]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 3]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertCreated()
            ->assertJsonPath('total', '300.00')
            ->assertJsonPath('status', Order::STATUS_PENDING)
            ->assertJsonPath('customer_name', 'Juan')
            ->assertJsonPath('customer_lastname', 'Pérez')
            ->assertJsonPath('customer_address', 'Av. Corrientes 1234');

        // Cart vaciado
        $this->assertEquals(0, CartItem::where('user_id', $buyer->id)->count());
        // Stock decrementado
        $this->assertEquals(7, $product->fresh()->stock);
    }

    #[Test]
    public function order_creation_fails_when_subtotal_is_below_minimum_purchase(): void
    {
        config(['store.min_purchase' => 50000]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        // qty=3 → subtotal=300 < 50000
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 3]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['cart']);

        // Cart NO se vacía
        $this->assertEquals(1, CartItem::where('user_id', $buyer->id)->count());
        // Stock NO se decrementa
        $this->assertEquals(10, $product->fresh()->stock);
    }

    #[Test]
    public function order_creation_succeeds_when_subtotal_meets_minimum_purchase(): void
    {
        config(['store.min_purchase' => 50000]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 10000, 'stock' => 10]);
        // qty=6 → subtotal=60000 >= 50000
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 6]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertCreated()
            ->assertJsonPath('total', '60000.00');
    }

    #[Test]
    public function cart_index_returns_min_purchase_and_remaining(): void
    {
        config(['store.min_purchase' => 50000]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 100]);
        // subtotal=10000

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/cart');

        $response->assertOk()
            ->assertJsonPath('min_purchase', 50000)
            ->assertJsonPath('remaining', 40000)
            ->assertJsonPath('meets_minimum', false);
    }

    #[Test]
    public function order_creation_fails_with_empty_cart(): void
    {
        $buyer = $this->makeBuyerWithProfile();

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['cart']);
    }

    #[Test]
    public function order_creation_fails_with_incomplete_profile(): void
    {
        $buyer = User::factory()->create([
            // sin lastname/phone/address/city
        ]);
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['profile']);
    }

    #[Test]
    public function order_creation_allows_shipping_data_override(): void
    {
        // Desactivamos el mínimo para este test
        config(['store.min_purchase' => 0]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', [
                'customer_address' => 'Otra dirección 999',
                'customer_city'    => 'Mar del Plata',
                'customer_notes'   => 'Dejar en portería',
            ]);

        $response->assertCreated()
            ->assertJsonPath('customer_address', 'Otra dirección 999')
            ->assertJsonPath('customer_city', 'Mar del Plata')
            ->assertJsonPath('customer_notes', 'Dejar en portería');
    }

    #[Test]
    public function buyer_can_list_only_their_orders(): void
    {
        $buyer  = $this->makeBuyerWithProfile();
        $other  = User::factory()->create();
        Order::factory()->count(2)->create(['user_id' => $buyer->id]);
        Order::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($buyer, 'sanctum')->getJson('/api/orders');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    #[Test]
    public function buyer_cannot_view_another_buyers_order(): void
    {
        $buyer = $this->makeBuyerWithProfile();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($buyer, 'sanctum')
            ->getJson("/api/orders/{$order->id}")
            ->assertNotFound();
    }

    #[Test]
    public function buyer_can_cancel_a_pending_order_and_stock_is_returned(): void
    {
        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);
        $order   = Order::factory()->create([
            'user_id' => $buyer->id,
            'status'  => Order::STATUS_PENDING,
            'total'   => 300,
        ]);
        $order->items()->create(['product_id' => $product->id, 'qty' => 3, 'price' => 100]);
        $product->decrement('stock', 3); // simular descuento del checkout
        $this->assertEquals(7, $product->fresh()->stock);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel", []);

        $response->assertOk()
            ->assertJsonPath('order.status', Order::STATUS_CANCELLED);

        // Stock devuelto
        $this->assertEquals(10, $product->fresh()->stock);
    }

    #[Test]
    public function buyer_cannot_cancel_a_confirmed_order(): void
    {
        $buyer = $this->makeBuyerWithProfile();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status'  => Order::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel", []);

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }
}
