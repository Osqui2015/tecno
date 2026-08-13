<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Muestra listado paginado y filtrable de cupones para el panel de administración.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->has('search') && ! empty($request->query('search'))) {
            $search = $request->query('search');
            $query->where('code', 'like', "%{$search}%");
        }

        if ($request->has('active') && $request->query('active') !== null && $request->query('active') !== '') {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortField = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $allowedSorts = ['id', 'code', 'type', 'value', 'uses_count', 'starts_at', 'expires_at', 'created_at'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $perPage = (int) $request->query('per_page', 15);
        $coupons = $query->paginate($perPage);

        return response()->json($coupons);
    }

    /**
     * Crea un nuevo cupón de descuento.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'         => 'required|string|max:50|unique:coupons,code',
            'type'         => 'required|in:percent,fixed',
            'value'        => 'required|numeric|min:0.01',
            'min_subtotal' => 'nullable|numeric|min:0',
            'max_uses'     => 'nullable|integer|min:1',
            'starts_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date|after_or_equal:starts_at',
            'active'       => 'boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['active'] = $validated['active'] ?? true;

        $coupon = Coupon::create($validated);

        return response()->json([
            'message' => 'Cupón creado correctamente',
            'coupon'  => $coupon,
        ], 201);
    }

    /**
     * Muestra la información detallada de un cupón.
     */
    public function show(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        return response()->json($coupon);
    }

    /**
     * Actualiza los datos de un cupón existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);

        $validated = $request->validate([
            'code'         => 'sometimes|required|string|max:50|unique:coupons,code,' . $coupon->id,
            'type'         => 'sometimes|required|in:percent,fixed',
            'value'        => 'sometimes|required|numeric|min:0.01',
            'min_subtotal' => 'nullable|numeric|min:0',
            'max_uses'     => 'nullable|integer|min:1',
            'starts_at'    => 'nullable|date',
            'expires_at'   => 'nullable|date|after_or_equal:starts_at',
            'active'       => 'boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $coupon->update($validated);

        return response()->json([
            'message' => 'Cupón actualizado correctamente',
            'coupon'  => $coupon,
        ]);
    }

    /**
     * Alterna el estado activo/inactivo de un cupón.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->active = ! $coupon->active;
        $coupon->save();

        return response()->json([
            'message' => $coupon->active ? 'Cupón activado' : 'Cupón desactivado',
            'coupon'  => $coupon,
        ]);
    }

    /**
     * Elimina un cupón.
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'Cupón eliminado correctamente',
        ]);
    }
}
