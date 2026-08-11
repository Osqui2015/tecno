<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeOrder(array $attrs = []): Order
    {
        $product = Product::factory()->create();
        $buyer   = User::factory()->create();

        $order = Order::factory()->create(array_merge([
            'user_id' => $buyer->id,
            'status'  => Order::STATUS_PENDING,
        ], $attrs));

        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'qty'        => 2,
            'price'      => $product->price,
        ]);

        return $order->fresh(['items.product', 'user']);
    }

    // ============================================================
    //  LIST
    // ============================================================

    #[Test]
    public function admin_can_list_all_orders(): void
    {
        $admin = $this->admin();
        $this->makeOrder();
        $this->makeOrder();
        $this->makeOrder();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders');

        $response->assertOk();
        $this->assertEquals(3, $response->json('total'));
    }

    #[Test]
    public function admin_can_filter_orders_by_status(): void
    {
        $admin = $this->admin();
        $this->makeOrder(['status' => Order::STATUS_PENDING]);
        $this->makeOrder(['status' => Order::STATUS_CONFIRMED]);
        $this->makeOrder(['status' => Order::STATUS_SHIPPED]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?status=confirmed');

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
    }

    #[Test]
    public function admin_can_filter_orders_by_daz_origin(): void
    {
        $admin = $this->admin();

        // Pedido con producto Daz
        $dazProduct = Product::factory()->fromDaz()->create();
        $buyer = User::factory()->create();
        $order1 = Order::factory()->create(['user_id' => $buyer->id]);
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'product_id' => $dazProduct->id,
        ]);

        // Pedido con producto manual
        $manProduct = Product::factory()->create(['external_id' => null]);
        $order2 = Order::factory()->create(['user_id' => $buyer->id]);
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'product_id' => $manProduct->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?source=daz');

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('daz', $response->json('data.0.origin_label'));
    }

    #[Test]
    public function admin_can_search_orders_by_customer_name(): void
    {
        $admin = $this->admin();
        $this->makeOrder(['customer_name' => 'María', 'customer_lastname' => 'García']);
        $this->makeOrder(['customer_name' => 'Juan',  'customer_lastname' => 'Pérez']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/orders?search=María');

        $this->assertEquals(1, $response->json('total'));
    }

    #[Test]
    public function admin_can_view_any_order(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    // ============================================================
    //  UPDATE STATUS
    // ============================================================

    #[Test]
    public function admin_can_change_order_status(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder(['status' => Order::STATUS_PENDING]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status' => Order::STATUS_CONFIRMED,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'confirmed');
    }

    #[Test]
    public function admin_can_set_admin_notes(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'admin_notes' => 'Cliente pidió envío express',
            ]);

        $response->assertOk()
            ->assertJsonPath('admin_notes', 'Cliente pidió envío express');
    }

    #[Test]
    public function cancelling_an_order_returns_stock_by_default(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['stock' => 5]);
        $order   = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'qty'        => 3,
            'price'      => $product->price,
        ]);
        $product->decrement('stock', 3); // simular descuento
        $this->assertEquals(2, $product->fresh()->stock);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status' => Order::STATUS_CANCELLED,
            ])
            ->assertOk();

        // Stock devuelto
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    #[Test]
    public function cancelling_with_return_stock_false_keeps_stock(): void
    {
        $admin = $this->admin();
        $product = Product::factory()->create(['stock' => 2]);
        $order   = Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        OrderItem::factory()->create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'qty'        => 3,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status'       => Order::STATUS_CANCELLED,
                'return_stock' => false,
            ])
            ->assertOk();

        $this->assertEquals(2, $product->fresh()->stock);
    }

    #[Test]
    public function invalid_status_is_rejected(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status' => 'inventado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function buyer_cannot_access_admin_orders(): void
    {
        $buyer = User::factory()->create();
        $this->makeOrder();

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    #[Test]
    public function order_origin_label_is_exposed(): void
    {
        $admin = $this->admin();
        $dazP = Product::factory()->fromDaz()->create();
        $manP = Product::factory()->create(['external_id' => null]);

        $order = Order::factory()->create();
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $dazP->id, 'qty' => 1, 'price' => 10]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $manP->id, 'qty' => 1, 'price' => 10]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('origin_label', 'mixed')
            ->assertJsonPath('items_count_daz', 1)
            ->assertJsonPath('items_count_manual', 1);
    }
}
