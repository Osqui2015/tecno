<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_get_dashboard_stats(): void
    {
        $admin = User::factory()->admin()->create();

        // Productos base para los pedidos
        $p1 = Product::factory()->create(['price' => 100, 'stock' => 10]);
        $p2 = Product::factory()->create(['price' => 250, 'stock' => 5]);
        $p3 = Product::factory()->create(['price' => 50, 'stock' => 0]); // sin stock
        Product::factory()->fromDaz()->create(['stock' => 5]);

        // Pedidos: 1 confirmado, 1 pending, 1 cancelado
        $buyer = User::factory()->create();
        $o1 = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_CONFIRMED, 'total' => 300]);
        OrderItem::factory()->create(['order_id' => $o1->id, 'product_id' => $p1->id, 'qty' => 3, 'price' => 100]);

        $o2 = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_PENDING, 'total' => 250]);
        OrderItem::factory()->create(['order_id' => $o2->id, 'product_id' => $p2->id, 'qty' => 1, 'price' => 250]);

        $o3 = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_CANCELLED, 'total' => 150]);
        OrderItem::factory()->create(['order_id' => $o3->id, 'product_id' => $p3->id, 'qty' => 1, 'price' => 150]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/stats');

        $response->assertOk()
            ->assertJsonPath('products.total', 4)        // 4 productos creados
            ->assertJsonPath('products.from_daz', 4)     // todos son Daz (factory default)
            ->assertJsonPath('products.out_of_stock', 1) // p3
            ->assertJsonPath('orders.total', 3)
            ->assertJsonPath('orders.pending', 1)
            ->assertJsonPath('orders.by_status.confirmed', 1)
            ->assertJsonPath('orders.by_status.pending', 1)
            ->assertJsonPath('orders.by_status.cancelled', 1)
            // Revenue excluye cancelados: 300 + 250 = 550
            ->assertJsonPath('revenue.total', 550)
            // Ticket promedio: (300 + 250) / 2 = 275
            ->assertJsonPath('revenue.avg_ticket', 275);

        // Top products: p1 (3 vendidos) está primero
        $topProduct = $response->json('top_products.0');
        $this->assertEquals($p1->id, $topProduct['id']);
        $this->assertEquals(3, (int) $topProduct['sold_qty']);
    }

    #[Test]
    public function buyer_cannot_access_stats(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertStatus(403);
    }
}
