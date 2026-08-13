<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Helpers para usar Cache con tags de forma SEGURA.
 *
 * En Laravel, los drivers `array` y `file` soportan tags nativamente.
 * Los drivers `database`, `redis` (algunas versiones) y `memcached` también.
 * PERO: el driver `database` de Laravel **NO soporta tags** por defecto
 * (lo cambiaron a partir de Laravel 9). Si el .env tiene
 *   CACHE_STORE=database
 * un Cache::tags(...)->remember(...) tira BadMethodCallException 500.
 *
 * Estos helpers caen automáticamente a cache plano si el driver no soporta
 * tags. La "invalidación por tags" pierde granularidad en drivers sin tags
 * (se hace flush total), pero el cache sigue funcionando.
 */
class CacheHelper
{
    /** ¿El driver actual soporta tags? */
    public static function supportsTags(): bool
    {
        try {
            Cache::tags(['__probe__']);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Cache::tags()->remember() con fallback.
     *
     * @template T
     * @param  string  $key
     * @param  array<int, string>  $tags
     * @param  int  $ttl  Segundos
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, array $tags, int $ttl, Closure $callback): mixed
    {
        try {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            // Driver no soporta tags: caemos a cache plano.
            return Cache::remember($key, $ttl, $callback);
        }
    }

    /**
     * Cache::tags()->flush() con fallback a flush total.
     *
     * @param  array<int, string>  $tags
     */
    public static function flush(array $tags): void
    {
        try {
            Cache::tags($tags)->flush();
        } catch (\Throwable $e) {
            Cache::flush();
        }
    }
}
