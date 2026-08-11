<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_setup_two_factor_and_get_secret(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/two-factor/setup');

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_url', 'recovery_codes']);

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    #[Test]
    public function status_endpoint_returns_disabled_initially(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/two-factor');

        $response->assertOk()
            ->assertJsonPath('enabled', false);
    }

    #[Test]
    public function verify_with_valid_code_activates_two_factor(): void
    {
        $user = User::factory()->create();
        // Setup primero
        $this->actingAs($user, 'sanctum')->postJson('/api/me/two-factor/setup');

        // Generar código TOTP válido
        $secret = \Illuminate\Support\Facades\Crypt::decryptString($user->fresh()->two_factor_secret);
        $google2fa = new Google2FA();
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/two-factor/verify', ['code' => $code]);

        $response->assertOk()
            ->assertJsonFragment(['message' => '2FA activado']);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    #[Test]
    public function verify_with_invalid_code_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/me/two-factor/setup');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/me/two-factor/verify', ['code' => '000000']);

        $response->assertStatus(422);
    }

    #[Test]
    public function login_requires_2fa_challenge_when_enabled(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('SOMESECRET'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_2fa', true);
    }

    #[Test]
    public function user_can_disable_two_factor(): void
    {
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('SOMESECRET'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/me/two-factor')
            ->assertOk();

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    #[Test]
    public function admin_endpoint_requires_2fa_works_for_non_admins_too(): void
    {
        // Un user no-admin también puede tener 2FA si quiere
        $user = User::factory()->create([
            'role' => User::ROLE_COMPRADOR,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/two-factor');

        $response->assertOk();
    }

    #[Test]
    public function two_factor_status_reflects_enabled_state(): void
    {
        $user = User::factory()->create([
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => encrypt('SECRET'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/me/two-factor');

        $response->assertOk()
            ->assertJsonPath('enabled', true);
    }
}
