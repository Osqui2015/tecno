<?php

namespace Tests\Feature;

use App\Mail\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_changing_order_status_sends_email_to_buyer(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create([
            'email' => 'comprador@test.com',
        ]);
        $order = Order::factory()->create([
            'user_id'  => $buyer->id,
            'status'   => Order::STATUS_PENDING,
            'customer_name' => 'Juan',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'status' => Order::STATUS_CONFIRMED,
            ])
            ->assertOk();

        Mail::assertSent(OrderStatusChanged::class, function ($mail) use ($buyer, $order) {
            return $mail->hasTo($buyer->email)
                && $mail->order->id === $order->id
                && $mail->newStatus === Order::STATUS_CONFIRMED
                && $mail->oldStatus === Order::STATUS_PENDING;
        });
    }

    #[Test]
    public function admin_updating_only_notes_does_not_send_email(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_CONFIRMED]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/orders/{$order->id}", [
                'admin_notes' => 'Solo una nota',
            ])
            ->assertOk();

        Mail::assertNothingSent();
    }

    #[Test]
    public function buyer_cancelling_order_sends_email(): void
    {
        Mail::fake();

        $buyer = User::factory()->create(['email' => 'buyer@test.com']);
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_PENDING]);

        $this->actingAs($buyer, 'sanctum')
            ->postJson("/api/orders/{$order->id}/cancel", [])
            ->assertOk();

        Mail::assertSent(OrderStatusChanged::class, function ($mail) {
            return $mail->newStatus === Order::STATUS_CANCELLED;
        });
    }
}
