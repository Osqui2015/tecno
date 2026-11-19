<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogSetting;
use App\Models\Category;
use App\Services\CatalogVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints admin para configurar qué productos se muestran en el catálogo
 * público, por proveedor (origen) y categoría.
 */
class CatalogSettingController extends Controller
{
    /**
     * GET /api/admin/catalog-settings
     *
     * Devuelve la configuración de cada proveedor con el detalle de las
     * categorías disponibles (para que el panel pueda listarlas).
     */
    public function index(): JsonResponse
    {
        $settings = CatalogSetting::allByOrigin();

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description']);

        $payload = [];

        foreach ($settings as $origin => $setting) {
            $payload[] = [
                'origin'             => $origin,
                'label'              => CatalogSetting::originLabels()[$origin] ?? $origin,
                'enabled'            => (bool) $setting->enabled,
                'filter_by_category' => (bool) $setting->filter_by_category,
                'category_ids'       => $setting->category_ids ?? [],
                'sort_order'         => (int) $setting->sort_order,
                'product_count'      => $this->countForOrigin($origin),
            ];
        }

        return response()->json([
            'settings'   => $payload,
            'categories' => $categories,
        ]);
    }

    /**
     * PATCH /api/admin/catalog-settings
     *
     * Body: { settings: [{ origin, enabled, filter_by_category, category_ids, sort_order }] }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings'                    => 'required|array',
            'settings.*.origin'           => 'required|string|in:' . implode(',', array_keys(CatalogSetting::originLabels())),
            'settings.*.enabled'          => 'required|boolean',
            'settings.*.filter_by_category'=> 'required|boolean',
            'settings.*.category_ids'     => 'sometimes|array',
            'settings.*.category_ids.*'   => 'integer|exists:categories,id',
            'settings.*.sort_order'       => 'sometimes|integer|min:0|max:999',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['settings'] as $row) {
                $setting = CatalogSetting::forOrigin($row['origin']);

                $setting->enabled     = (bool) $row['enabled'];
                $setting->sort_order  = (int) ($row['sort_order'] ?? 0);
                $setting->category_ids = array_map('intval', $row['category_ids'] ?? []);

                // Si se activa el filtro por categoría, las categorías vacías
                // hiding todo: se desactiva el filtro para no dejar el
                // proveedor sin productos visibles.
                $setting->filter_by_category = (bool) $row['filter_by_category'];

                if ($setting->filter_by_category && empty($setting->category_ids)) {
                    $setting->filter_by_category = false;
                    $setting->category_ids       = [];
                }

                $setting->save();
            }
        });

        // La configuración cambió: hay que limpiar el cache del catálogo
        // y el de los productos para que el storefront la refleje ya.
        CatalogVisibility::flushCache();
        \Illuminate\Support\Facades\Cache::flush();

        return response()->json([
            'message'  => 'Configuración del catálogo actualizada',
            'settings' => $this->index()->getData(true)['settings'],
        ]);
    }

    /**
     * GET /api/admin/catalog-settings/stats
     *
     * Cantidad de productos visibles/ocultos por proveedor, para que el
     * panel muestre el impacto de la configuración.
     */
    public function stats(): JsonResponse
    {
        $settings = CatalogSetting::allByOrigin();
        $rows     = [];

        foreach ($settings as $origin => $setting) {
            $total = $this->countForOrigin($origin);

            // Productos activos del proveedor, sin filtro de categoría.
            $available = \App\Models\Product::query()
                ->where('origin', $origin)
                ->available()
                ->count();

            // Productos visibles con la configuración guardada (con filtro).
            $visible = \App\Models\Product::query()
                ->where('origin', $origin)
                ->available()
                ->when($setting->filter_by_category && ! empty($setting->category_ids), function ($q) use ($setting) {
                    $q->whereIn('category_id', array_map('intval', $setting->category_ids));
                })
                ->count();

            $rows[] = [
                'origin'    => $origin,
                'label'     => CatalogSetting::originLabels()[$origin] ?? $origin,
                'total'     => $total,
                'available' => $available,
                'visible'   => $visible,
                'hidden'    => max(0, $total - $visible),
            ];
        }

        return response()->json(['stats' => $rows]);
    }

    /**
     * Cantidad de productos (activos o no) de un origen.
     */
    private function countForOrigin(string $origin): int
    {
        return \App\Models\Product::query()
            ->where('origin', $origin)
            ->count();
    }
}
