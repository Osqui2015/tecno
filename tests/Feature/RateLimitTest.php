<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_endpoint_blocks_after_too_many_attempts(): void
    {
        // 5 intentos permitidos por minuto. El 6º debe ser 429.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'noexiste@example.com',
                'password' => 'wrong',
            ])->assertStatus(422); // credenciales inválidas → 422
        }

        // El 6º intento debe ser rate limited
        $response = $this->postJson('/api/login', [
            'email' => 'noexiste@example.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429)
            ->assertJsonFragment([
                'message' => 'Demasiados intentos. Probá de nuevo en un minuto.',
            ]);
    }

    #[Test]
    public function register_endpoint_blocks_after_too_many_attempts(): void
    {
        // Mismo email → el rate limit cuenta todos los intentos bajo la misma key.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Test',
                'email' => 'sam@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        // El 6º intento (mismo email) debe ser rate limited.
        $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'sam@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(429);
    }
}
