<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Devuelve configuración pública de la tienda que el frontend necesita
 * para armar links de WhatsApp (botón flotante, mensaje de checkout, etc.).
 *
 * No requiere auth: el número de WhatsApp y la dirección del local son
 * datos visibles en la web pública, no son sensibles.
 */
class StoreInfoController extends Controller
{
    /**
     * GET /api/store-info
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'name'             => config('store.name'),
            'address'          => config('store.address'),
            'phone'            => config('store.phone'),
            'whatsapp_number'  => config('store.whatsapp_number'),
            'min_purchase'     => (float) config('store.min_purchase'),
        ]);
    }
}
