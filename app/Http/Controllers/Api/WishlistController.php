<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * GET /api/wishlist
     */
    public function index(Request $request): JsonResponse
    {
        $items = WishlistItem::with('product')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'items' => $items,
            'count' => $items->count(),
        ]);
    }

    /**
     * POST /api/wishlist
     * Body: { product_id: N }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $item = WishlistItem::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $data['product_id'],
        ]);

        return response()->json([
            'message' => 'Agregado a favoritos',
            'item'    => $item->load('product'),
        ], 201);
    }

    /**
     * DELETE /api/wishlist/{productId}
     */
    public function destroy(Request $request, int $productId): JsonResponse
    {
        $deleted = WishlistItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'No estaba en favoritos'], 404);
        }

        return response()->json(['message' => 'Eliminado de favoritos']);
    }
}
