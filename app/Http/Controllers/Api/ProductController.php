<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->active()
            ->whereNotNull('external_id');

        // 1) Búsqueda por término
        if ($request->filled('search')) {
            $searchTerm = $request->string('search')->toString();
            $query->search($searchTerm);
        }

        // 2) Categoría
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
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

        // 5) Ordenamiento
        $sortBy = $request->query('sort_by', 'name_asc');
        match ($sortBy) {
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

                $availableBrands = Product::active()
                    ->whereNotNull('external_id')
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '')
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
            'search'      => $request->string('search')->toString(),
            'category_id' => $request->integer('category_id'),
            'brand'       => $request->string('brand')->toString(),
            'min_price'   => $request->float('min_price'),
            'max_price'   => $request->float('max_price'),
            'sort_by'     => $sortBy,
            'per_page'    => $perPage,
            'page'        => $request->integer('page', 1),
        ];

        return 'products:public:' . md5(json_encode($params));
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
            fn () => Product::with('category')->active()->findOrFail($id)
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

        $base = Product::active()
            ->where('id', '!=', $id)
            ->where('stock', '>', 0);

        // Si tiene categoría, priorizamos misma categoría. Si no, devolvemos cualquier otro.
        $product = CacheHelper::remember(
            "products:show:{$id}",
            ['products:public'],
            self::CACHE_TTL_SECONDS,
            fn () => Product::active()->find($id)
        );

        if ($product && $product->category_id) {
            $base->where('category_id', $product->category_id);
        }

        $related = $base->orderByDesc('stock')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $related,
            'total' => $related->count(),
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
