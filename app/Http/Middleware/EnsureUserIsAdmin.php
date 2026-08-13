<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea cualquier request cuyo usuario autenticado no sea admin.
 *
 * Considera AMBAS fuentes de permisos para no quedar inconsistente:
 *  - Spatie roles (hasRole)
 *  - Columna `users.role` (fallback para usuarios sin rol Spatie asignado)
 *
 * Uso en rutas:
 *   Route::middleware(['auth:sanctum', 'admin'])->group(...)
 *
 * Requiere que la request ya haya pasado por 'auth:sanctum'
 * (es decir, $request->user() debe estar resuelto).
 */
class EnsureUserIsAdmin
{
    /** Roles Spatie que dan acceso a endpoints admin. */
    private const ADMIN_ROLES = [
        'super-admin',
        'admin',
        'admin-pedidos',
        'admin-productos',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole(self::ADMIN_ROLES)) {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren permisos de administrador.',
            ], 403);
        }

        return $next($request);
    }
}
