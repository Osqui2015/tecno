<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'name'  => 'Producto X',
            'price' => 100,
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function buyer_user_is_forbidden_from_admin_routes(): void
    {
        $buyer = User::factory()->create(); // role = comprador por default

        $response = $this->actingAs($buyer, 'sanctum')
            ->postJson('/api/admin/products', [
                'name'  => 'Producto X',
                'price' => 100,
            ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Acceso denegado. Se requieren permisos de administrador.',
            ]);
    }

    #[Test]
    public function admin_user_can_access_admin_routes(): void
    {
        $admin = User::factory()->admin()->create();

        // Esperamos 422 (validation) y NO 401/403 (el middleware admin ya pasó).
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products', []);

        $this->assertNotEquals(401, $response->status(), 'No debe ser 401');
        $this->assertNotEquals(403, $response->status(), 'No debe ser 403');
        $this->assertEquals(422, $response->status(), 'Debe ser 422 (validation)');
    }

    #[Test]
    public function buyer_user_can_access_their_own_orders_endpoint(): void
    {
        $buyer = User::factory()->create();

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/orders');

        $response->assertOk();
    }

    #[Test]
    public function register_endpoint_defaults_to_comprador_role(): void
    {
        $payload = [
            'name'                  => 'Nuevo',
            'email'                 => 'nuevo@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_COMPRADOR);
    }

    #[Test]
    public function register_endpoint_ignores_role_attempt_to_become_admin(): void
    {
        $payload = [
            'name'                  => 'Hacker',
            'email'                 => 'hacker@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin', // intento malicioso
        ];

        $response = $this->postJson('/api/register', $payload);

        $response->assertCreated()
            ->assertJsonPath('user.role', User::ROLE_COMPRADOR); // sigue siendo comprador
    }
}
