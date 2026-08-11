<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     *   - per_page: default 15
     */
    public function index(Request $request): JsonResponse
    {
        $hasSearch = $request->filled('search');

        // Tecno-Rexs vende únicamente productos del catálogo de Dazimportadora.
        // Filtramos por external_id NOT NULL para garantizar eso.

        if ($hasSearch) {
            $searchTerm = $request->string('search')->toString();
            $query = Product::query()
                ->active()
                ->whereNotNull('external_id')
                ->search($searchTerm);

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            $results = $query->paginate($request->integer('per_page', 15));
        } else {
            $query = Product::with('category')
                ->active()
                ->whereNotNull('external_id');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            $perPage = min($request->integer('per_page', 60), 200);
            $results = $query->orderBy('name')->paginate($perPage);
        }

        return response()->json($results);
    }

    /**
     * GET /api/products/searchproduc
     */
    public function search(Request $request): JsonResponse
    {
        $searchTerm = $request->string('search')->toString();

        $query = Product::query()
            ->active()
            ->whereNotNull('external_id')
            ->search($searchTerm);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $results = $query->paginate($request->integer('per_page', 60));
        return response()->json($results);
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
