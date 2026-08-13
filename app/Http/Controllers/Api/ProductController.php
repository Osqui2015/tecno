<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Endpoints PÚBLICOS del catálogo.
 * Las operaciones admin (crear/editar/eliminar/markup) viven en Admin/ProductController.
 */
class ProductController extends Controller
{
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
        $results = $query->paginate($perPage);

        // Obtener marcas únicas para el sidebar de filtros
        $availableBrands = Product::active()
            ->whereNotNull('external_id')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand', DB::raw('count(*) as count'))
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();

        return response()->json([
            'data'             => $results->items(),
            'current_page'     => $results->currentPage(),
            'last_page'        => $results->lastPage(),
            'per_page'         => $results->perPage(),
            'total'            => $results->total(),
            'available_brands' => $availableBrands,
        ]);
    }

    /**
     * GET /api/products/searchproduc
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
        $product = Product::with('category')
            ->active()
            ->findOrFail($id);

        return response()->json($product);
    }
}
