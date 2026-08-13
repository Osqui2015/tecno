<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Política de acceso a productos para el panel admin.
 * Centraliza la lógica que antes estaba chequeada "a mano" en controllers.
 */
class ProductPolicy
{
    /**
     * Roles con permiso de escritura sobre productos.
     * Lectura está abierta a cualquier admin (se chequea en el middleware EnsureUserIsAdmin).
     */
    private const WRITE_ROLES = ['super-admin', 'admin', 'admin-productos'];

    public function viewAny(User $user): bool
    {
        return $this->isAnyAdmin($user);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->isAnyAdmin($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function bulkMarkup(User $user): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function import(User $user): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function export(User $user): bool
    {
        return $this->isAnyAdmin($user);
    }

    private function isAnyAdmin(User $user): bool
    {
        return $user->hasRole(['super-admin', 'admin', 'admin-pedidos', 'admin-productos']);
    }
}
