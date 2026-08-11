<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderStatusChanged;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\WhatsAppMessageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * GET /api/admin/orders
     *
     * Query params:
     *   - status: filtra por estado exacto
     *   - source: 'daz' (todos los items son de Daz) | 'manual' | 'mixed'
     *   - user_id: filtra por comprador
     *   - search: nombre, apellido, email o id del pedido
     *   - date_from / date_to: rango de fechas (created_at)
     *   - per_page: default 25
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items.product', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('source')) {
            $source = $request->string('source')->toString();
            // Filtramos por IDs de orden según el origen de sus items.
            $dazProductIds = Product::where('origin', 'daz')->pluck('id');
            $tucProductIds = Product::where('origin', 'tuc')->pluck('id');
            $manualProductIds = Product::where(function ($q) {
                $q->whereNull('origin')->orWhere('origin', 'manual');
            })->pluck('id');

            if ($source === 'daz') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $dazProductIds))
                      ->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $tucProductIds->merge($manualProductIds)));
            } elseif ($source === 'tuc') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $tucProductIds))
                      ->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $dazProductIds->merge($manualProductIds)));
            } elseif ($source === 'manual') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $manualProductIds))
                      ->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $dazProductIds->merge($tucProductIds)));
            } elseif ($source === 'mixed') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $dazProductIds->merge($tucProductIds)))
                      ->whereHas('items', fn ($q) => $q->whereIn('product_id', $manualProductIds));
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $query->where(function ($q) use ($term) {
                $q->where('customer_name', 'like', "%{$term}%")
                  ->orWhere('customer_lastname', 'like', "%{$term}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('email', 'like', "%{$term}%")
                                                       ->orWhere('name', 'like', "%{$term}%"))
                  ->orWhere('id', (int) (is_numeric($term) ? $term : 0));
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to'));
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $orders  = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items.product', 'user'])->findOrFail($id);
        return response()->json($order);
    }

    /**
     * PATCH /api/admin/orders/{id}
     *
     * Body:
     *   { status: 'confirmed' | 'preparing' | 'shipped' | ... }
     *   { admin_notes: 'nota interna' }
     *   { return_stock: true }   ← si se cancela, devuelve stock
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        $data = $request->validate([
            'status'      => ['sometimes', Rule::in(Order::STATUSES)],
            'admin_notes' => 'sometimes|nullable|string|max:5000',
            'return_stock'=> 'sometimes|boolean',
        ]);

        $oldStatus = $order->status;

        return DB::transaction(function () use ($order, $data, $oldStatus, $request) {
            // Si pasa a 'cancelled' y return_stock = true → devolver stock
            if (
                ! empty($data['status'])
                && $data['status'] === Order::STATUS_CANCELLED
                && $oldStatus !== Order::STATUS_CANCELLED
                && ($data['return_stock'] ?? true)
            ) {
                foreach ($order->items as $item) {
                    $item->product()->increment('stock', $item->qty);
                }
            }

            // Si pasa de 'cancelled' a otro estado, opcionalmente re-descontar stock
            if (
                $oldStatus === Order::STATUS_CANCELLED
                && ! empty($data['status'])
                && $data['status'] !== Order::STATUS_CANCELLED
                && ($data['return_stock'] ?? false) === false
            ) {
                foreach ($order->items as $item) {
                    if ($item->product->stock >= $item->qty) {
                        $item->product()->decrement('stock', $item->qty);
                    }
                }
            }

            // Capturar valor original de admin_notes ANTES de modificar
            $originalNotes = $order->admin_notes;

            if (isset($data['status'])) {
                $order->status = $data['status'];
            }
            if (array_key_exists('admin_notes', $data)) {
                $order->admin_notes = $data['admin_notes'];
            }
            $order->save();

            // Audit log si cambió algo
            $statusChanged = isset($data['status']) && $data['status'] !== $oldStatus;
            $notesChanged  = array_key_exists('admin_notes', $data)
                && $data['admin_notes'] !== $originalNotes;

            if ($statusChanged || $notesChanged) {
                AuditLog::create([
                    'action'       => 'order.updated',
                    'description'  => $statusChanged
                        ? "Pedido #{$order->id}: {$oldStatus} → {$order->status}"
                        : "Pedido #{$order->id}: notas actualizadas",
                    'subject_type' => Order::class,
                    'subject_id'   => $order->id,
                    'actor_type'   => User::class,
                    'actor_id'     => $request->user()?->id,
                    'meta'         => [
                        'status_changed' => $statusChanged,
                        'notes_changed'  => $notesChanged,
                        'old_status'     => $oldStatus,
                        'new_status'     => $order->status,
                        'return_stock'   => $data['return_stock'] ?? null,
                    ],
                    'ip_address'   => $request->ip(),
                ]);
            }

            // Enviar email al comprador si cambió el estado
            if ($statusChanged && $order->user && $order->user->email) {
                Mail::to($order->user->email)->send(
                    new OrderStatusChanged($order->fresh(), $oldStatus, $order->status)
                );
            }

            return response()->json($order->fresh()->load(['items.product', 'user']));
        });
    }

    /**
     * DELETE /api/admin/orders/{id}
     * Eliminar pedido (soft: solo si no tiene items críticos).
     * Para mantener el historial, normalmente se usa cancel, no delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Pedido eliminado']);
    }

    /**
     * GET /api/admin/orders/{id}/whatsapp-preview
     * Devuelve el mensaje WhatsApp (preview) según el estado actual
     * de los items (confirmed_available / confirmed_qty).
     * NO modifica nada, solo genera el texto.
     */
    public function whatsappPreview(int $id): JsonResponse
    {
        $order = Order::with('items.product')->findOrFail($id);
        $builder = new WhatsAppMessageBuilder($order);

        return response()->json([
            'message'      => $builder->build(),
            'whatsapp_url' => $builder->whatsappUrl(),
            'has_phone'    => (bool) $order->customer_phone,
            'order_id'     => $order->id,
        ]);
    }

    /**
     * POST /api/admin/orders/{id}/confirm-availability
     *
     * El admin marca qué productos del pedido están disponibles y cuáles no,
     * opcionalmente con cantidad parcial, y el sistema:
     *   1) Actualiza cada order_item con confirmed_available y confirmed_qty.
     *   2) Marca el pedido como 'confirmed' (si no estaba ya).
     *   3) Genera el mensaje de WhatsApp listo para enviar.
     *   4) Devuelve whatsapp_url para abrir wa.me en una nueva pestaña.
     *
     * Body:
     *   {
     *     "items": [
     *       { "item_id": 12, "available": true,  "qty": 2 },
     *       { "item_id": 13, "available": false, "qty": 1 }
     *     ],
     *     "admin_notes": "Cliente avisado por WhatsApp",
     *     "auto_send": true
     *   }
     */
    public function confirmAvailability(Request $request, int $id): JsonResponse
    {
        $order = Order::with('items.product')->findOrFail($id);

        $data = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.item_id'        => 'required|integer|exists:order_items,id',
            'items.*.available'      => 'required|boolean',
            'items.*.qty'            => 'sometimes|nullable|integer|min:0',
            'admin_notes'            => 'sometimes|nullable|string|max:5000',
            'auto_send'              => 'sometimes|boolean',
        ]);

        $itemIds = collect($data['items'])->pluck('item_id')->all();
        $orderItemIds = $order->items->pluck('id')->all();

        // Seguridad: solo se pueden modificar items que pertenecen a ESTE pedido.
        $invalid = array_diff($itemIds, $orderItemIds);
        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'items' => 'Algunos items no pertenecen a este pedido: ' . implode(', ', $invalid),
            ]);
        }

        return DB::transaction(function () use ($order, $data, $request) {
            foreach ($data['items'] as $row) {
                $item = OrderItem::where('order_id', $order->id)
                    ->where('id', $row['item_id'])
                    ->firstOrFail();

                $item->confirmed_available = (bool) $row['available'];

                if ($row['available']) {
                    // Si el admin no especifica qty, mantener la original.
                    $qty = isset($row['qty']) && $row['qty'] !== null
                        ? (int) $row['qty']
                        : (int) $item->qty;
                    $qty = max(0, min($qty, (int) $item->qty)); // cap a lo pedido
                    $item->confirmed_qty = $qty;
                } else {
                    $item->confirmed_qty = 0;
                }
                $item->save();
            }

            $oldStatus = $order->status;
            $order->status = Order::STATUS_CONFIRMED;
            $order->confirmed_at = now();
            $order->confirmed_by = $request->user()?->id;

            if (array_key_exists('admin_notes', $data)) {
                $order->admin_notes = $data['admin_notes'];
            }

            if ($data['auto_send'] ?? true) {
                $order->whatsapp_last_sent_at = now();
            }

            $order->save();

            // Audit log
            AuditLog::create([
                'action'       => 'order.confirmed',
                'description'  => "Pedido #{$order->id}: disponibilidad confirmada por el admin",
                'subject_type' => Order::class,
                'subject_id'   => $order->id,
                'actor_type'   => User::class,
                'actor_id'     => $request->user()?->id,
                'meta'         => [
                    'items_reviewed' => count($data['items']),
                    'old_status'     => $oldStatus,
                    'new_status'     => $order->status,
                ],
                'ip_address'   => $request->ip(),
            ]);

            // Generar mensaje
            $builder = new WhatsAppMessageBuilder($order->fresh(['items.product']));
            $message = $builder->build();
            $whatsappUrl = $builder->whatsappUrl();

            // (Opcional) enviar email al cliente avisando que el pedido fue confirmado
            try {
                if ($order->user && $order->user->email) {
                    Mail::to($order->user->email)->send(
                        new OrderStatusChanged($order->fresh(), $oldStatus, $order->status)
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar email de confirmación', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }

            return response()->json([
                'order'        => $order->fresh(['items.product', 'user']),
                'message'      => $message,
                'whatsapp_url' => $whatsappUrl,
                'has_phone'    => (bool) $order->customer_phone,
            ]);
        });
    }

    /**
     * GET /api/admin/orders/export/csv
     * Exporta los pedidos filtrados a CSV (con los mismos filtros que index()).
     */
    public function exportCsv(Request $request)
    {
        $query = Order::with(['items.product', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('source')) {
            $source = $request->string('source');
            $dazProductIds    = Product::whereNotNull('external_id')->pluck('id');
            $manualProductIds = Product::whereNull('external_id')->pluck('id');
            if ($source === 'daz') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $dazProductIds))
                      ->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $manualProductIds));
            } elseif ($source === 'manual') {
                $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $manualProductIds))
                      ->whereDoesntHave('items', fn ($q) => $q->whereIn('product_id', $dazProductIds));
            }
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date('date_to'));
        }

        $orders = $query->orderByDesc('created_at')->limit(5000)->get();

        $filename = 'pedidos-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($orders) {
            $out = fopen('php://output', 'w');
            // BOM para que Excel detecte UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            // Header
            fputcsv($out, [
                'ID', 'Fecha', 'Estado', 'Origen',
                'Cliente', 'Email', 'Teléfono',
                'Dirección', 'Ciudad', 'CP',
                'Items', 'Items Daz', 'Items Tuc', 'Items Manual', 'Total',
            ]);
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->id,
                    $o->created_at->format('Y-m-d H:i'),
                    $o->status,
                    $o->origin_label,
                    $o->customer_full_name,
                    $o->user?->email ?? '',
                    $o->customer_phone ?? '',
                    $o->customer_address ?? '',
                    $o->customer_city ?? '',
                    $o->customer_zip ?? '',
                    $o->items->count(),
                    $o->items_count_daz,
                    $o->items_count_tuc,
                    $o->items_count_manual,
                    $o->total,
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }
}
