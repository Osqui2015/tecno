<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea cualquier request cuyo usuario autenticado no sea admin.
 *
 * Uso en rutas:
 *   Route::middleware(['auth:sanctum', 'admin'])->group(...)
 *
 * Requiere que la request ya haya pasado por 'auth:sanctum'
 * (es decir, $request->user() debe estar resuelto).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if (! method_exists($user, 'hasRole') || ! $user->hasRole(['super-admin', 'admin', 'admin-pedidos', 'admin-productos'])) {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren permisos de administrador.',
            ], 403);
        }

        return $next($request);
    }
}
