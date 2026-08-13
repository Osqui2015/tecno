<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Política de acceso a pedidos.
 */
class OrderPolicy
{
    private const READ_ROLES = ['super-admin', 'admin', 'admin-pedidos'];
    private const WRITE_ROLES = ['super-admin', 'admin', 'admin-pedidos'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole(self::READ_ROLES);
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole(self::READ_ROLES)) {
            return true;
        }
        // El comprador solo ve sus propios pedidos.
        return $order->user_id === $user->id;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function confirmAvailability(User $user, Order $order): bool
    {
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function cancel(User $user, Order $order): bool
    {
        // Comprador puede cancelar solo si está pending y es suyo.
        if ($order->user_id === $user->id && $order->canBeCancelledByBuyer()) {
            return true;
        }
        // Admin puede cancelar en cualquier estado.
        return $user->hasRole(self::WRITE_ROLES);
    }

    public function export(User $user): bool
    {
        return $user->hasRole(self::READ_ROLES);
    }
}
