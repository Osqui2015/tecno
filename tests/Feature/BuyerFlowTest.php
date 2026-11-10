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
    public function buyer_can_clear_optional_profile_fields(): void
    {
        $buyer = $this->makeBuyerWithProfile();

        $response = $this->actingAs($buyer, 'sanctum')
            ->patchJson('/api/me/profile', [
                'city'     => '',
                'zip_code' => '',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.city', null)
            ->assertJsonPath('user.zip_code', null);
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

    #[Test]
    public function order_charges_final_price_including_markup_and_snapshots_in_order_items(): void
    {
        config(['store.min_purchase' => 0]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create([
            'price'          => 100,
            'markup_percent' => 20, // final_price = 120.00
            'stock'          => 10,
        ]);

        $cartItem = CartItem::create([
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
            'qty'        => 2,
        ]);

        // Subtotal en cart debe reflejar final_price (120 * 2 = 240)
        $this->assertEquals(240.00, $cartItem->subtotal);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertCreated()
            ->assertJsonPath('total', '240.00');

        $order = Order::with('items')->latest()->first();
        $this->assertEquals(240.00, (float) $order->total);
        $this->assertEquals(120.00, (float) $order->items->first()->price);
    }

    #[Test]
    public function buyer_without_lastname_can_create_order(): void
    {
        config(['store.min_purchase' => 0]);

        $buyer = User::factory()->create([
            'name'     => 'Carlos',
            'lastname' => null,
            'phone'    => '+54 11 5555-9999',
            'address'  => 'Calle Falsa 123',
            'city'     => 'Rosario',
        ]);

        $product = Product::factory()->create(['price' => 500, 'stock' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertCreated()
            ->assertJsonPath('customer_name', 'Carlos')
            ->assertJsonPath('customer_lastname', null);
    }

    #[Test]
    public function manual_products_without_external_id_appear_in_catalog(): void
    {
        \App\Support\CacheHelper::flush(['products:public']);

        $category = \App\Models\Category::factory()->create();
        $manualProduct = Product::create([
            'name'        => 'Producto Manual Test',
            'price'       => 1500,
            'stock'       => 10,
            'category_id' => $category->id,
            'active'      => true,
            'external_id' => null,
            'origin'      => 'manual',
        ]);

        $response = $this->getJson('/api/products');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($manualProduct->id, $ids);
    }

    #[Test]
    public function calling_related_does_not_poison_show_cache_relationship(): void
    {
        \App\Support\CacheHelper::flush(['products:public']);

        $category = \App\Models\Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'stock'       => 10,
            'active'      => true,
        ]);

        // 1) Llamar primero a /related
        $this->getJson("/api/products/{$product->id}/related")->assertOk();

        // 2) Llamar a /show inmediatamente (debe leer el cache)
        $showResponse = $this->getJson("/api/products/{$product->id}");
        $showResponse->assertOk()
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('category.name', $category->name);
    }

    #[Test]
    public function checkout_normalizes_coupon_code_case_insensitively(): void
    {
        config(['store.min_purchase' => 0]);

        $buyer   = $this->makeBuyerWithProfile();
        $product = Product::factory()->create(['price' => 1000, 'stock' => 5]);
        CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'qty' => 1]);

        $coupon = \App\Models\Coupon::create([
            'code'     => 'PROMO10',
            'type'     => 'percent',
            'value'    => 10,
            'max_uses' => 5,
            'active'   => true,
        ]);

        // Se envía en minúsculas y con espacios
        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', [
                'coupon_code' => '  promo10  ',
            ]);

        $response->assertCreated()
            ->assertJsonPath('discount', '100.00')
            ->assertJsonPath('total', '900.00');

        $this->assertEquals(1, $coupon->fresh()->uses_count);
    }

    #[Test]
    public function product_with_stock_1_to_4_remains_active_and_visible_in_catalog(): void
    {
        \App\Support\CacheHelper::flush(['products:public']);

        $product = Product::factory()->create([
            'name'   => 'Últimas 3 Unidades',
            'stock'  => 3,
            'active' => true,
        ]);

        $this->assertTrue($product->fresh()->active);

        $response = $this->getJson('/api/products');
        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($product->id, $ids);
    }

    #[Test]
    public function purchasing_last_stock_sets_product_inactive_and_flushes_cache(): void
    {
        config(['store.min_purchase' => 0]);
        \App\Support\CacheHelper::flush(['products:public']);

        $buyer = $this->makeBuyerWithProfile();
        $product = Product::factory()->create([
            'price'  => 500,
            'stock'  => 2,
            'active' => true,
        ]);

        // Pre-calentar caché de producto
        $this->getJson("/api/products/{$product->id}")->assertOk();

        CartItem::create([
            'user_id'    => $buyer->id,
            'product_id' => $product->id,
            'qty'        => 2,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/orders', []);

        $response->assertCreated();

        $fresh = $product->fresh();
        $this->assertEquals(0, $fresh->stock);
        $this->assertFalse((bool) $fresh->active);

        // El endpoint público ya no debe devolverlo como activo en el catálogo
        $catalogResponse = $this->getJson('/api/products');
        $catalogResponse->assertOk();
        $ids = collect($catalogResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($product->id, $ids);
    }

    #[Test]
    public function cancelling_order_restores_stock_and_reactivates_inactive_product(): void
    {
        config(['store.min_purchase' => 0]);
        \App\Support\CacheHelper::flush(['products:public']);

        $buyer = $this->makeBuyerWithProfile();
        $product = Product::factory()->create([
            'price'  => 1000,
            'stock'  => 0,
            'active' => false,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status'  => Order::STATUS_PENDING,
            'total'   => 1000,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'qty'        => 1,
            'price'      => 1000,
        ]);

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel", []);

        $response->assertOk()
            ->assertJsonPath('order.status', Order::STATUS_CANCELLED);

        $fresh = $product->fresh();
        $this->assertEquals(1, $fresh->stock);
        $this->assertTrue((bool) $fresh->active);
    }
}
