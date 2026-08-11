<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Aseguramos que los roles de Spatie existan antes de cada test.
     * RefreshDatabase los borra junto con todo lo demás.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Solo crear roles si la tabla existe (RefreshDatabase la crea).
        // Tests sin RefreshDatabase (como ExampleTest) no tendrán roles.
        if (! \Illuminate\Support\Facades\Schema::hasTable('roles')) {
            return;
        }

        Role::firstOrCreate(['name' => User::ROLE_COMPRADOR, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => User::ROLE_ADMIN,     'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin',         'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-pedidos',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin-productos',     'guard_name' => 'web']);
    }
}
