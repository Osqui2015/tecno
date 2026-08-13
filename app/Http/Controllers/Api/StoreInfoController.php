<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreInfo;
use Illuminate\Http\JsonResponse;

/**
 * Devuelve la configuración pública de la tienda (WhatsApp, dirección, redes).
 *
 * Es pública: el número de WhatsApp y los links de redes ya están visibles
 * en la web, no son sensibles.
 *
 * La fuente de verdad es la tabla `store_infos` (editable desde el panel
 * admin). El config() queda como fallback si por algún motivo no hay
 * registro todavía.
 */
class StoreInfoController extends Controller
{
    /**
     * GET /api/store-info
     */
    public function index(): JsonResponse
    {
        $info = StoreInfo::current();

        return response()->json([
            'name'              => $info->name ?: config('store.name'),
            'address'           => $info->address ?: config('store.address'),
            'phone'             => $info->phone ?: config('store.phone'),
            'whatsapp_number'   => $info->whatsapp_number ?: config('store.whatsapp_number'),
            'whatsapp_digits'   => $info->whatsappDigits(),
            'instagram_url'     => $info->instagram_url,
            'facebook_url'      => $info->facebook_url,
            'tiktok_url'        => $info->tiktok_url,
            'email_contact'     => $info->email_contact,
            'schedule'          => $info->schedule,
            'short_description' => $info->short_description,
            'min_purchase'      => (float) ($info->min_purchase ?: config('store.min_purchase')),
        ]);
    }
}
