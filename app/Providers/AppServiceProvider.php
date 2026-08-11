<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Rate limiters nombrados (se aplican en routes con middleware('throttle:NOMBRE')).
     */
    protected function configureRateLimiting(): void
    {
        // Auth endpoints: limitar por IP + email para evitar fuerza bruta
        // 5 intentos por minuto. Si falla 5 veces, devuelve 429.
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip() . '|' . $request->input('email', ''))
                    ->response(function () {
                        return response()->json([
                            'message' => 'Demasiados intentos. Probá de nuevo en un minuto.',
                        ], 429);
                    }),
            ];
        });

        // Escritura admin: 60 acciones por minuto (un admin activo no llega a esto)
        RateLimiter::for('admin-write', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Checkout: 10 pedidos por hora por usuario (evita spam/abuso)
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
