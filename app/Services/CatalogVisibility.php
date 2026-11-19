<?php

namespace App\Services;

use App\Models\CatalogSetting;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Aplica la configuración de visibilidad del catálogo (CatalogSetting)
 * a las consultas de productos.
 *
 * Reglas:
 *  - Si el origen está deshabilitado (`enabled = false`), sus productos
 *    NO aparecen en el catálogo público.
 *  - Si tiene `filter_by_category = true`, solo aparecen los productos
 *    cuya categoría esté en `category_ids`. Los productos sin categoría
 *    asignada (category_id = 0) quedan ocultos.
 *  - Si no hay configuración cargada para un origen, se muestra todo
 *    (comportamiento por defecto / retrocompatible).
 */
class CatalogVisibility
{
    /** TTL del cache de la configuración (cambia poco, no necesita ir a la DB en cada request). */
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Configuración cacheada de todos los orígenes.
     *
     * @return array<string, array{enabled: bool, category_ids: ?array<int,int>, filter_by_category: bool}>
     */
    public static function settings(): array
    {
        return Cache::remember('catalog:settings', self::CACHE_TTL_SECONDS, function () {
            $out = [];

            foreach (CatalogSetting::allByOrigin() as $origin => $setting) {
                $out[$origin] = [
                    'enabled'            => (bool) $setting->enabled,
                    'category_ids'       => $setting->category_ids === null
                        ? null
                        : array_map('intval', $setting->category_ids),
                    'filter_by_category' => (bool) $setting->filter_by_category,
                ];
            }

            return $out;
        });
    }

    /**
     * Invalida el cache de la configuración (después de guardar cambios).
     */
    public static function flushCache(): void
    {
        Cache::forget('catalog:settings');
    }

    /**
     * Aplica el filtro de visibilidad al query de productos.
     *
     * @param  Builder  $query
     * @param  bool  $includeManual  Si los productos manuales (sin origen o
     *                              con origin='manual') también deben quedar
     *                              sujetos a la configuración. Por defecto
     *                              los manuales siempre se muestran.
     */
    public static function apply(Builder $query, bool $includeManual = true): Builder
    {
        $settings = self::settings();

        // Si no hay ninguna configuración, se muestra todo (retrocompatible).
        if (empty($settings)) {
            return $query;
        }

        // Orígenes visibles (enabled = true).
        $visibleOrigins = [];
        foreach ($settings as $origin => $cfg) {
            if ($cfg['enabled']) {
                $visibleOrigins[] = $origin;
            }
        }

        // Los manuales se muestran siempre salvo que estén deshabilitados
        // explícitamente en la configuración.
        $manualEnabled = $includeManual && ($settings['manual']['enabled'] ?? true);

        // Categorías por origen que aplican filtro estricto.
        $strictByOrigin = [];
        foreach ($settings as $origin => $cfg) {
            if ($cfg['enabled'] && $cfg['filter_by_category'] && $cfg['category_ids'] !== null) {
                $strictByOrigin[$origin] = $cfg['category_ids'];
            }
        }

        return $query->where(function (Builder $q) use ($visibleOrigins, $manualEnabled, $strictByOrigin) {
            $first = true;

            foreach ($strictByOrigin as $origin => $categoryIds) {
                $q->orWhere(function (Builder $sub) use ($origin, $categoryIds) {
                    $sub->where('origin', $origin)
                        ->whereIn('category_id', $categoryIds);
                });
                $first = false;
            }

            // Orígenes visibles sin filtro estricto de categoría.
            $looseOrigins = array_values(array_diff($visibleOrigins, array_keys($strictByOrigin)));

            if (! empty($looseOrigins)) {
                $q->orWhereIn('origin', $looseOrigins);
            }

            // Manuales (origin IS NULL o 'manual').
            if ($manualEnabled) {
                $q->orWhere(function (Builder $sub) {
                    $sub->whereNull('origin')->orWhere('origin', 'manual');
                });
            }
        });
    }

    /**
     * IDs de las categorías que están visibles para un origen dado.
     *
     * @return array<int, int>
     */
    public static function visibleCategoryIds(string $origin): array
    {
        $cfg = self::settings()[$origin] ?? null;

        if ($cfg === null || ! $cfg['enabled']) {
            return [];
        }

        if (! $cfg['filter_by_category'] || $cfg['category_ids'] === null) {
            return Category::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $cfg['category_ids'];
    }
}
