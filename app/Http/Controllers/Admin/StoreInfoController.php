<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints admin para editar los datos de la tienda
 * (WhatsApp, dirección, redes, mínimo de compra, etc).
 *
 * El modelo es singleton: siempre se edita el único registro existente.
 */
class StoreInfoController extends Controller
{
    /**
     * GET /api/admin/store-info
     */
    public function show(): JsonResponse
    {
        return response()->json(StoreInfo::current());
    }

    /**
     * PATCH /api/admin/store-info
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'sometimes|nullable|string|max:255',
            'address'          => 'sometimes|nullable|string|max:255',
            'phone'            => 'sometimes|nullable|string|max:30',
            // El número de WhatsApp se guarda tal cual, lo normalizamos
            // a dígitos al momento de usarlo (en StoreInfo::whatsappDigits).
            'whatsapp_number'  => 'sometimes|nullable|string|max:30',
            'instagram_url'    => 'sometimes|nullable|url|max:255',
            'facebook_url'     => 'sometimes|nullable|url|max:255',
            'tiktok_url'       => 'sometimes|nullable|url|max:255',
            'email_contact'    => 'sometimes|nullable|email|max:255',
            'schedule'         => 'sometimes|nullable|string|max:255',
            'short_description'=> 'sometimes|nullable|string|max:1000',
            'min_purchase'     => 'sometimes|nullable|numeric|min:0',
        ]);

        $info = StoreInfo::current();
        $info->fill($data);
        $info->save();

        return response()->json([
            'message' => 'Configuración actualizada',
            'store'   => $info,
        ]);
    }
}
