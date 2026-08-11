<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_price_update_creates_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 1000, 'markup_percent' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/products/{$product->id}", [
                'price' => 1500,
            ])
            ->assertOk();

        $log = AuditLog::where('action', 'product.updated')->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->actor_id);
        $this->assertEquals($product->id, $log->subject_id);
        $this->assertEquals(1000.0, $log->meta['before']['price']);
        $this->assertEquals(1500.0, $log->meta['after']['price']);
    }

    #[Test]
    public function product_update_without_changes_does_not_log(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['price' => 1000, 'markup_percent' => 10]);

        // Mismos valores → no debería crear log
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/products/{$product->id}", [
                'price' => 1000,
                'markup_percent' => 10,
            ])
            ->assertOk();

        $this->assertEquals(0, AuditLog::count());
    }

    #[Test]
    public function bulk_markup_creates_single_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(5)->create(['markup_percent' => 0]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products/bulk-markup', ['percent' => 30])
            ->assertOk();

        $log = AuditLog::where('action', 'product.bulk_markup')->first();
        $this->assertNotNull($log);
        $this->assertEquals(30.0, $log->meta['percent']);
        $this->assertEquals(5, $log->meta['count']);
    }

    #[Test]
    public function order_status_change_creates_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => Order::STATUS_PENDING]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status' => Order::STATUS_CONFIRMED,
                'admin_notes' => 'Pedido verificado',
            ])
            ->assertOk();

        $log = AuditLog::where('action', 'order.updated')->first();
        $this->assertNotNull($log);
        $this->assertEquals($order->id, $log->subject_id);
        $this->assertTrue($log->meta['status_changed']);
        $this->assertTrue($log->meta['notes_changed']);
    }

    #[Test]
    public function admin_can_list_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLog::factory()->count(3)->create([
            'actor_type' => User::class,
            'actor_id'   => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/audit-logs');

        $response->assertOk();
        $this->assertEquals(3, $response->json('total'));
    }

    #[Test]
    public function buyer_cannot_access_audit_logs(): void
    {
        AuditLog::factory()->count(2)->create();
        $buyer = User::factory()->create();

        $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/audit-logs')
            ->assertStatus(403);
    }
}
