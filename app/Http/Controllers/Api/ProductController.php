<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogVisibility;
use App\Support\CacheHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Endpoints PÚBLICOS del catálogo.
 * Las operaciones admin (crear/editar/eliminar/markup) viven en Admin/ProductController.
 */
class ProductController extends Controller
{
    /** TTL del cache público del catálogo. */
    private const CACHE_TTL_SECONDS = 300; // 5 minutos

    /**
     * GET /api/products
     * Query params opcionales:
     *   - category_id: filtra por categoría
     *   - search: busca por nombre / descripción / sku
     *   - min_price: precio mínimo
     *   - max_price: precio máximo
     *   - brand: filtra por marca
     *   - sort_by: price_asc | price_desc | newest | name_asc | name_desc
     *   - per_page: default 15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category')
            ->available();

        // 0) Visibilidad del catálogo (configuración admin por proveedor).
        //    Los productos de proveedores deshabilitados o de categorías no
        //    seleccionadas no se muestran en el catálogo público.
        CatalogVisibility::apply($query);

        // 1) Búsqueda por término (utiliza Scout si hay driver configurado con soporte de acentos, fallback a LIKE)
        if ($request->filled('search')) {
            $searchTerm = trim($request->string('search')->toString());
            $normalized = Str::ascii($searchTerm);

            if (config('scout.driver')) {
                $scoutIds = Product::search($searchTerm)->keys()->all();
                if ($normalized !== $searchTerm) {
                    $normIds = Product::search($normalized)->keys()->all();
                    $scoutIds = array_values(array_unique(array_merge($scoutIds, $normIds)));
                }

                if (! empty($scoutIds)) {
                    $query->whereIn('id', $scoutIds);
                } else {
                    $query->search($searchTerm);
                }
            } else {
                $query->search($searchTerm);
            }
        }

        // 2) Categoría (incluye tanto category_id principal como categories_external)
        if ($request->filled('category_id')) {
            $query->inCategory($request->integer('category_id'));
        }

        // 3) Marca
        if ($request->filled('brand')) {
            $brand = $request->string('brand')->toString();
            $query->where('brand', $brand);
        }

        // 4) Rango de Precios
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->float('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->float('max_price'));
        }

        // 4.5) Origen / Fuente (daz | tuc | local / manual)
        if ($request->filled('source') || $request->filled('origin')) {
            $source = $request->string('source')->toString() ?: $request->string('origin')->toString();
            if ($source === 'daz') {
                $query->fromDaz();
            } elseif ($source === 'tuc') {
                $query->fromTuc();
            } elseif ($source === 'local' || $source === 'manual') {
                $query->manual();
            }
        }

        // 5) Ordenamiento
        $sortBy = $request->query('sort_by', 'name_asc');
        match ($sortBy) {
            'featured'   => $this->applyFeaturedOrder($query),
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest'     => $query->orderBy('created_at', 'desc'),
            'name_desc'  => $query->orderBy('name', 'desc'),
            default      => $query->orderBy('name', 'asc'),
        };

        $perPage = min($request->integer('per_page', 16), 100);

        // Cacheamos la respuesta completa. La key incluye todos los filtros
        // para no servir respuestas incorrectas entre combinaciones.
        $cacheKey = $this->buildCacheKey($request, $perPage, $sortBy);

        $payload = CacheHelper::remember(
            $cacheKey,
            ['products:public'],
            self::CACHE_TTL_SECONDS,
            function () use ($query, $perPage, $request) {
                $results = $query->paginate($perPage);

                $brandsQuery = Product::available()
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '');

                // Mismo filtro de visibilidad que el listado.
                CatalogVisibility::apply($brandsQuery);

                if ($request->filled('category_id')) {
                    $brandsQuery->inCategory($request->integer('category_id'));
                }
                if ($request->filled('source') || $request->filled('origin')) {
                    $src = $request->string('source')->toString() ?: $request->string('origin')->toString();
                    if ($src === 'daz') {
                        $brandsQuery->fromDaz();
                    } elseif ($src === 'tuc') {
                        $brandsQuery->fromTuc();
                    } elseif ($src === 'local' || $src === 'manual') {
                        $brandsQuery->manual();
                    }
                }

                $availableBrands = $brandsQuery
                    ->select('brand', DB::raw('count(*) as count'))
                    ->groupBy('brand')
                    ->orderBy('brand')
                    ->get();

                return [
                    'data'             => $results->items(),
                    'current_page'     => $results->currentPage(),
                    'last_page'        => $results->lastPage(),
                    'per_page'         => $results->perPage(),
                    'total'            => $results->total(),
                    'available_brands' => $availableBrands,
                ];
            }
        );

        return response()->json($payload);
    }

    /**
     * Construye la key de cache incluyendo todos los filtros.
     */
    private function buildCacheKey(Request $request, int $perPage, string $sortBy): string
    {
        $params = [
            'search'      => trim($request->string('search')->toString()),
            'category_id' => $request->integer('category_id'),
            'brand'       => $request->string('brand')->toString(),
            'source'      => $request->string('source')->toString() ?: $request->string('origin')->toString(),
            'min_price'   => $request->float('min_price'),
            'max_price'   => $request->float('max_price'),
            'sort_by'     => $sortBy,
            'per_page'    => $perPage,
            'page'        => $request->integer('page', 1),
        ];

        return 'products:public:' . md5(json_encode($params));
    }

    /**
     * Aplica el orden de productos destacados, priorizando productos navideños
     * con imagen disponible y stock, seguido del resto por stock.
     */
    protected function applyFeaturedOrder(Builder $query): Builder
    {
        $navidadCatId = (int) (Category::where('name', 'like', '%navidad%')->value('id') ?? 74);

        return $query->orderByRaw("
            CASE
                WHEN (
                    category_id = {$navidadCatId}
                    OR categories_external LIKE '%Navidad%'
                    OR name LIKE '%navid%'
                    OR name LIKE '%NAVID%'
                    OR name LIKE '%Navid%'
                    OR name LIKE '%papa noel%'
                    OR name LIKE '%PAPA NOEL%'
                    OR name LIKE '%arbol%'
                    OR name LIKE '%árbol%'
                    OR name LIKE '%Árbol%'
                    OR name LIKE '%ARBOL%'
                )
                AND name NOT LIKE '%camara%'
                AND name NOT LIKE '%CAMARA%'
                AND image IS NOT NULL
                AND image != '' THEN
                    CASE
                        WHEN (
                            name LIKE '%arbol%'
                            OR name LIKE '%árbol%'
                            OR name LIKE '%Árbol%'
                            OR name LIKE '%ARBOL%'
                        ) THEN 2
                        ELSE 1
                    END
                ELSE 0
            END DESC
        ")
        ->orderByDesc('stock')
        ->orderByDesc('created_at');
    }

    /**
     * GET /api/products/searchproduc
     * (alias deprecado mantenido por retrocompatibilidad)
     */
    public function search(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * GET /api/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = CacheHelper::remember(
            "products:show:{$id}",
            ['products:public'],
            self::CACHE_TTL_SECONDS,
            fn () => Product::with('category')
                ->active()
                ->tap(fn (Builder $q) => CatalogVisibility::apply($q))
                ->findOrFail($id)
        );

        return response()->json($product);
    }

    /**
     * GET /api/products/{id}/related
     * Devuelve hasta N productos de la misma categoría, con stock, excluyendo el actual.
     * Ordena por stock DESC (prioriza los que están en stock) y nombre ASC como desempate.
     */
    public function related(int $id, Request $request): JsonResponse
    {
        $limit = min((int) $request->integer('limit', 4), 12);
        if ($limit < 1) {
            $limit = 4;
        }

        $related = CacheHelper::remember(
            "products:related:{$id}:{$limit}",
            ['products:public'],
            self::CACHE_TTL_SECONDS,
            function () use ($id, $limit) {
                $product = CacheHelper::remember(
                    "products:show:{$id}",
                    ['products:public'],
                    self::CACHE_TTL_SECONDS,
                    fn () => Product::with('category')->active()->find($id)
                );

                $query = Product::with('category')
                    ->active()
                    ->where('id', '!=', $id)
                    ->where('stock', '>', 0);

                // Los relacionados también respetan la visibilidad del catálogo.
                CatalogVisibility::apply($query);

                if ($product && $product->category_id) {
                    $query->where('category_id', $product->category_id);
                }

                return $query->orderByDesc('stock')
                    ->orderBy('name')
                    ->limit($limit)
                    ->get();
            }
        );

        return response()->json([
            'data' => $related,
            'total' => count($related),
        ]);
    }

    /**
     * POST /api/compare
     * Body: { ids: [1, 2, 3, 4] }
     * Devuelve los productos solicitados para mostrar en una tabla comparativa.
     */
    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:2|max:4',
            'ids.*' => 'integer|exists:products,id',
        ]);

        $products = Product::with('category')
            ->active()
            ->whereIn('id', $data['ids'])
            ->get();

        // Mantenemos el orden de los ids del request
        $ordered = collect($data['ids'])->map(fn ($id) => $products->firstWhere('id', $id))->filter();

        return response()->json([
            'data' => $ordered->values(),
            'total' => $ordered->count(),
        ]);
    }
}
