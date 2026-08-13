<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /**
     * GET /api/cart
     * Devuelve el carrito del user autenticado con totales y conteo de items.
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $total       = $items->sum(fn ($i) => (float) $i->subtotal);
        // Solo cuentan para el contador los items con qty > 0
        $itemsCount  = $items->where('qty', '>', 0)->sum('qty');
        $minPurchase = (float) config('store.min_purchase');

        return response()->json([
            'items'         => $items,
            'total'         => number_format($total, 2, '.', ''), // mismo formato que Order->total
            'items_count'   => (int) $itemsCount,
            'min_purchase'  => $minPurchase,
            'remaining'     => max(0, $minPurchase - $total),
            'meets_minimum' => $total >= $minPurchase,
        ]);
    }

    /**
     * POST /api/cart/items
     * Agrega un producto al carrito. Si ya existe, incrementa qty.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'qty'        => 'sometimes|integer|min:1|max:999',
        ]);

        $product = Product::findOrFail($data['product_id']);

        if (! $product->active) {
            throw ValidationException::withMessages([
                'product_id' => ['Este producto no está disponible.'],
            ]);
        }

        $addQty = (int) ($data['qty'] ?? 1);

        $item = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        $currentQty = $item?->qty ?? 0;
        $newQty     = $currentQty + $addQty;

        if ($product->stock < $newQty) {
            throw ValidationException::withMessages([
                'qty' => ["Stock insuficiente. Disponible: {$product->stock}"],
            ]);
        }

        if ($item) {
            $item->qty = $newQty;
            $item->save();
        } else {
            $item = CartItem::create([
                'user_id'    => $request->user()->id,
                'product_id' => $product->id,
                'qty'        => $addQty,
            ]);
        }

        return response()->json([
            'message' => 'Producto agregado al carrito',
            'item'    => $item->load('product'),
        ], 201);
    }

    /**
     * PATCH /api/cart/items/{id}
     * Cambia la cantidad de un item (sin agregar otro).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Permitimos qty=0 para que el frontend pueda "marcar para quitar"
        // (el item sigue en el carrito, sólo se borra al confirmar).
        $data = $request->validate([
            'qty' => 'required|integer|min:0|max:999',
        ]);

        $item = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $newQty = (int) $data['qty'];

        if ($newQty > 0 && $item->product->stock < $newQty) {
            throw ValidationException::withMessages([
                'qty' => ["Stock insuficiente. Disponible: {$item->product->stock}"],
            ]);
        }

        $item->qty = $newQty;
        $item->save();

        return response()->json([
            'message' => $newQty === 0
                ? 'Item marcado para quitar'
                : 'Cantidad actualizada',
            'item'    => $item,
        ]);
    }

    /**
     * DELETE /api/cart/items/{id}
     * Quita un item del carrito.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = CartItem::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        if ($deleted === 0) {
            return response()->json(['message' => 'Item no encontrado'], 404);
        }

        return response()->json(['message' => 'Item eliminado']);
    }

    /**
     * DELETE /api/cart
     * Vacía todo el carrito del user autenticado.
     */
    public function clear(Request $request): JsonResponse
    {
        $count = CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Carrito vaciado',
            'removed' => $count,
        ]);
    }
}
