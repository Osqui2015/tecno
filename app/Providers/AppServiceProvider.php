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
        $this->syncStoreConfigFromDatabase();
    }

    /**
     * Sincroniza config('store.*') con el registro de la tabla `store_infos`.
     *
     * Asi, todo el código que ya usa `config('store.min_purchase')`,
     * `config('store.name')`, etc. (OrderController, WhatsAppMessageBuilder,
     * etc.) sigue funcionando sin cambios, pero toma los valores editables
     * desde el panel admin.
     *
     * Si la tabla no existe aún (migración pendiente) o no hay registro,
     * no hace nada y se mantienen los defaults del config file.
     */
    protected function syncStoreConfigFromDatabase(): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('store_infos')) {
                return;
            }

            $info = \App\Models\StoreInfo::current();

            $overrides = array_filter([
                'store.name'            => $info->name,
                'store.address'         => $info->address,
                'store.phone'           => $info->phone,
                'store.whatsapp_number' => $info->whatsapp_number,
            ], fn ($v) => $v !== null && $v !== '');

            if (! empty($overrides)) {
                config($overrides);
            }

            if ($info->min_purchase !== null) {
                config(['store.min_purchase' => (float) $info->min_purchase]);
            }
        } catch (\Throwable $e) {
            // Si falla (ej. durante instalación antes de migrar), no rompemos el boot.
        }
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
