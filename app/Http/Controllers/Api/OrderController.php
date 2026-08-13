<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * GET /api/orders
     * Lista los pedidos del comprador autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($orders);
    }

    /**
     * GET /api/orders/{id}
     * Detalle de un pedido del comprador autenticado.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items.product')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    /**
     * POST /api/orders
     * Crea un pedido a partir del carrito persistido del user.
     * Hace snapshot de los datos de envío del user (o de los override del request).
     * Vacía el carrito al finalizar.
     * Decrementa stock de los productos.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Override opcional de los datos de envío del perfil
            'customer_name'     => 'sometimes|nullable|string|max:255',
            'customer_lastname' => 'sometimes|nullable|string|max:255',
            'customer_phone'    => 'sometimes|nullable|string|max:30',
            'customer_address'  => 'sometimes|nullable|string|max:255',
            'customer_city'     => 'sometimes|nullable|string|max:255',
            'customer_zip'      => 'sometimes|nullable|string|max:20',
            'customer_notes'    => 'sometimes|nullable|string|max:1000',
            'coupon_code'       => 'sometimes|nullable|string|max:50',
        ]);

        $user = $request->user();

        // Resolver datos de envío finales: override del request > perfil del user
        $ship = [
            'name'     => $data['customer_name']     ?? $user->name,
            'lastname' => $data['customer_lastname'] ?? $user->lastname,
            'phone'    => $data['customer_phone']    ?? $user->phone,
            'address'  => $data['customer_address']  ?? $user->address,
            'city'     => $data['customer_city']     ?? $user->city,
            'zip'      => $data['customer_zip']      ?? $user->zip_code,
            'notes'    => $data['customer_notes']    ?? null,
        ];

        // Validar que los datos críticos estén completos
        $missing = array_filter([
            'nombre'     => $ship['name'],
            'apellido'   => $ship['lastname'],
            'teléfono'   => $ship['phone'],
            'dirección'  => $ship['address'],
            'ciudad'     => $ship['city'],
        ], fn ($v) => blank($v));

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'profile' => [
                    'Faltan datos de envío: ' . implode(', ', array_keys($missing)) .
                    '. Completá tu perfil o envialos en el checkout.',
                ],
            ]);
        }

        return DB::transaction(function () use ($user, $ship, $data) {
            // 1) Traer items del carrito del user
            // Filtramos los items con qty=0 (marcados para quitar pero no confirmados)
            $cartItems = CartItem::with('product')
                ->where('user_id', $user->id)
                ->where('qty', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['El carrito está vacío.'],
                ]);
            }

            $subtotal    = 0;
            $orderItems  = [];

            foreach ($cartItems as $ci) {
                $product = $ci->product;

                if (! $product || ! $product->active) {
                    throw ValidationException::withMessages([
                        'cart' => ["El producto '{$product?->name}' ya no está disponible."],
                    ]);
                }

                if ($product->stock < $ci->qty) {
                    throw ValidationException::withMessages([
                        'cart' => ["Stock insuficiente para '{$product->name}'. Disponible: {$product->stock}"],
                    ]);
                }

                $lineTotal = (float) $product->price * $ci->qty;
                $subtotal += $lineTotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'qty'        => $ci->qty,
                    'price'      => $product->price,
                ];

                $product->decrement('stock', $ci->qty);
            }

            // 1.5) Validar compra mínima
            $minPurchase = (float) config('store.min_purchase');
            if ($subtotal < $minPurchase) {
                $minFormatted = number_format($minPurchase, 0, ',', '.');
                $subFormatted = number_format($subtotal, 0, ',', '.');
                throw ValidationException::withMessages([
                    'cart' => [
                        "La compra minima es de $" . $minFormatted .
                        ". Tu carrito actual es de $" . $subFormatted . ".",
                    ],
                ]);
            }

            // 2) Aplicar cupón si se envió
            $coupon = null;
            $discount = 0;
            if (! empty($data['coupon_code'])) {
                $coupon = Coupon::where('code', $data['coupon_code'])->first();
                if (! $coupon) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['Cupón no encontrado.'],
                    ]);
                }
                if (! $coupon->isAvailable()) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['Este cupón no está activo o ya expiró.'],
                    ]);
                }
                $discount = $coupon->discountFor($subtotal);
                if ($discount === 0.0) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['El cupón no aplica a este subtotal.'],
                    ]);
                }
                $coupon->increment('uses_count');
            }

            $total = max(0, round($subtotal - $discount, 2));

            // 3) Crear pedido
            $order = Order::create([
                'user_id'           => $user->id,
                'subtotal'          => $subtotal,
                'discount'          => $discount,
                'coupon_id'         => $coupon?->id,
                'total'             => $total,
                'status'            => Order::STATUS_PENDING,
                'shipping_address'  => trim("{$ship['address']}, {$ship['city']} ({$ship['zip']})"),
                'customer_name'     => $ship['name'],
                'customer_lastname' => $ship['lastname'],
                'customer_phone'    => $ship['phone'],
                'customer_address'  => $ship['address'],
                'customer_city'     => $ship['city'],
                'customer_zip'      => $ship['zip'],
                'customer_notes'    => $ship['notes'],
            ]);

            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
            }

            // 4) Vaciar carrito
            CartItem::where('user_id', $user->id)->delete();

            return response()->json(
                $order->load('items.product', 'coupon'),
                201
            );
        });
    }

    /**
     * POST /api/orders/{id}/cancel
     * El comprador cancela su pedido (solo si está en 'pending').
     * Devuelve el stock de los productos.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $order->canBeCancelledByBuyer()) {
            throw ValidationException::withMessages([
                'status' => ["No podés cancelar un pedido en estado '{$order->status}'."],
            ]);
        }

        return DB::transaction(function () use ($order) {
            // Devolver stock
            foreach ($order->items as $item) {
                $item->product()->increment('stock', $item->qty);
            }

            $oldStatus = $order->status;
            $order->status = Order::STATUS_CANCELLED;
            $order->save();

            // Email al comprador
            if ($order->user && $order->user->email) {
                Mail::to($order->user->email)->send(
                    new OrderStatusChanged($order->fresh()->load(['items.product', 'user']), $oldStatus, Order::STATUS_CANCELLED)
                );
            }

            return response()->json([
                'message' => 'Pedido cancelado',
                'order'   => $order->fresh()->load('items.product'),
            ]);
        });
    }
}
