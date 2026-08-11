<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_export_orders_as_csv(): void
    {
        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create(['email' => 'comprador@test.com']);
        $product = Product::factory()->fromDaz()->create();
        $order = Order::factory()->create([
            'user_id'  => $buyer->id,
            'status'   => Order::STATUS_CONFIRMED,
            'total'    => 5000,
            'customer_name' => 'Test',
            'customer_lastname' => 'User',
            'customer_phone' => '+54 11 5555',
            'customer_address' => 'Av Test 123',
            'customer_city' => 'CABA',
            'customer_zip' => 'C1000',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 2500,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/orders/export/csv');

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('pedidos-', $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString($order->id, $body);
        $this->assertStringContainsString('confirmed', $body);
        $this->assertStringContainsString('comprador@test.com', $body);
        $this->assertStringContainsString('Test User', $body);
        $this->assertStringContainsString('5000', $body);
    }

    #[Test]
    public function csv_export_respects_status_filter(): void
    {
        $admin = User::factory()->admin()->create();
        Order::factory()->create(['status' => Order::STATUS_CONFIRMED]);
        Order::factory()->create(['status' => Order::STATUS_PENDING]);
        Order::factory()->create(['status' => Order::STATUS_CANCELLED]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/admin/orders/export/csv?status=confirmed');

        $response->assertOk();
        $body = $response->streamedContent();
        // Header row + 1 confirmed order = 2 lines
        $lines = array_filter(explode("\n", trim($body)));
        $this->assertCount(2, $lines);
    }

    #[Test]
    public function buyer_cannot_export_csv(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->get('/api/admin/orders/export/csv')
            ->assertStatus(403);
    }
}
