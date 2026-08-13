<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouponAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createBuyer(): User
    {
        return User::factory()->create();
    }

    #[Test]
    public function admin_can_list_coupons(): void
    {
        $admin = $this->createAdmin();
        Coupon::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/coupons');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'current_page']);
    }

    #[Test]
    public function non_admin_cannot_access_admin_coupons(): void
    {
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/coupons');

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_create_a_coupon(): void
    {
        $admin = $this->createAdmin();

        $payload = [
            'code'         => 'PROMO50',
            'type'         => 'percent',
            'value'        => 50,
            'min_subtotal' => 1000,
            'max_uses'     => 100,
            'active'       => true,
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', $payload);

        $response->assertCreated()
            ->assertJsonPath('coupon.code', 'PROMO50')
            ->assertJsonPath('coupon.type', 'percent');

        $this->assertDatabaseHas('coupons', ['code' => 'PROMO50']);
    }

    #[Test]
    public function admin_can_update_a_coupon(): void
    {
        $admin = $this->createAdmin();
        $coupon = Coupon::factory()->create(['code' => 'VIEJO']);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/coupons/{$coupon->id}", [
                'code'  => 'NUEVO',
                'value' => 20,
            ]);

        $response->assertOk()
            ->assertJsonPath('coupon.code', 'NUEVO');

        $this->assertDatabaseHas('coupons', ['code' => 'NUEVO']);
    }

    #[Test]
    public function admin_can_toggle_coupon_active_status(): void
    {
        $admin = $this->createAdmin();
        $coupon = Coupon::factory()->create(['active' => true]);

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/coupons/{$coupon->id}/toggle");

        $response->assertOk()
            ->assertJsonPath('coupon.active', false);

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'active' => false]);
    }

    #[Test]
    public function admin_can_delete_a_coupon(): void
    {
        $admin = $this->createAdmin();
        $coupon = Coupon::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/coupons/{$coupon->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }
}
