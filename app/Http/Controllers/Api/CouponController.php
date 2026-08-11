<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * POST /api/coupons/validate
     * Body: { code: "VERANO25", subtotal: 5000 }
     * Devuelve { coupon, discount, final_subtotal }
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'     => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $data['code'])->first();

        if (! $coupon) {
            throw ValidationException::withMessages([
                'code' => ['Cupón no encontrado.'],
            ]);
        }

        if (! $coupon->isAvailable()) {
            throw ValidationException::withMessages([
                'code' => ['Este cupón no está activo o ya expiró.'],
            ]);
        }

        $subtotal  = (float) $data['subtotal'];
        $discount  = $coupon->discountFor($subtotal);

        if ($discount === 0.0) {
            throw ValidationException::withMessages([
                'code' => ['El subtotal no alcanza el mínimo requerido por el cupón.'],
            ]);
        }

        return response()->json([
            'coupon'         => $coupon,
            'discount'       => $discount,
            'final_subtotal' => round($subtotal - $discount, 2),
        ]);
    }
}
