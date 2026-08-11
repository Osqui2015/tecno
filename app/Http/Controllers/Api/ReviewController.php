<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * GET /api/products/{product}/reviews
     * Lista reseñas de un producto (público).
     */
    public function index(int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $reviews = Review::with('user:id,name')
            ->where('product_id', $product->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        // Stats agregados
        $stats = Review::where('product_id', $product->id)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
            ->first();

        return response()->json([
            'reviews'    => $reviews,
            'avg_rating' => (float) round((float) ($stats->avg_rating ?? 0), 2),
            'total'      => (int) ($stats->total ?? 0),
        ]);
    }

    /**
     * POST /api/products/{product}/reviews
     * Crear reseña. Requiere auth + haber comprado el producto.
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();

        // ¿Ya tiene reseña?
        if (Review::where('user_id', $user->id)->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'review' => ['Ya dejaste una reseña para este producto.'],
            ]);
        }

        // ¿Compró este producto?
        $hasPurchased = OrderItem::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('status', '!=', Order::STATUS_CANCELLED);
            })
            ->where('product_id', $product->id)
            ->exists();

        if (! $hasPurchased) {
            throw ValidationException::withMessages([
                'review' => ['Solo podés reseñar productos que hayas comprado.'],
            ]);
        }

        $review = Review::create([
            'user_id'              => $user->id,
            'product_id'           => $product->id,
            'rating'               => $data['rating'],
            'comment'              => $data['comment'] ?? null,
            'is_verified_purchase' => true,
        ]);

        return response()->json([
            'message' => 'Reseña creada',
            'review'  => $review->load('user:id,name'),
        ], 201);
    }

    /**
     * DELETE /api/reviews/{review}
     * El dueño puede borrar la suya. Admin puede borrar cualquiera.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $user = $request->user();

        $isOwner = $review->user_id === $user->id;
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole(['super-admin', 'admin']);

        if (! $isOwner && ! $isAdmin) {
            return response()->json(['message' => 'Sin permiso'], 403);
        }

        $review->delete();
        return response()->json(['message' => 'Reseña eliminada']);
    }
}
