<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAdminTest extends TestCase
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
    public function admin_can_list_users(): void
    {
        $admin = $this->createAdmin();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'current_page']);
    }

    #[Test]
    public function non_admin_cannot_access_user_management(): void
    {
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer, 'sanctum')
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_create_new_user_profile(): void
    {
        $admin = $this->createAdmin();

        $payload = [
            'name'     => 'Carlos',
            'lastname' => 'Gómez',
            'email'    => 'carlos@example.com',
            'password' => 'password123',
            'role'     => User::ROLE_COMPRADOR,
            'phone'    => '+54 11 4444-5555',
            'city'     => 'Cordoba',
        ];

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/users', $payload);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'carlos@example.com')
            ->assertJsonPath('user.role', User::ROLE_COMPRADOR);

        $this->assertDatabaseHas('users', ['email' => 'carlos@example.com']);
    }

    #[Test]
    public function admin_can_update_user_profile_and_role(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createBuyer();

        $response = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}", [
                'name' => 'Nombre Modificado',
                'role' => User::ROLE_ADMIN,
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Nombre Modificado')
            ->assertJsonPath('user.role', User::ROLE_ADMIN);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => User::ROLE_ADMIN]);
    }

    #[Test]
    public function admin_can_delete_another_user_profile(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createBuyer();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$user->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[Test]
    public function admin_cannot_delete_their_own_profile(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$admin->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar tu propio perfil de usuario.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
