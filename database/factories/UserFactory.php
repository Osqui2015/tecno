<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            // Default: comprador
            $role = Role::firstOrCreate(['name' => User::ROLE_COMPRADOR, 'guard_name' => 'web']);
            $user->assignRole($role);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            // Garantizar que los roles existan (los tests usan RefreshDatabase)
            $adminRole = Role::firstOrCreate(['name' => User::ROLE_ADMIN, 'guard_name' => 'web']);
            $superRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
            $user->syncRoles([$adminRole, $superRole]);
        });
    }

    public function comprador(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::firstOrCreate(['name' => User::ROLE_COMPRADOR, 'guard_name' => 'web']);
            $user->syncRoles([$role]);
        });
    }
}
