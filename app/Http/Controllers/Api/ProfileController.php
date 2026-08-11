<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * GET /api/me/profile
     * Devuelve los datos del perfil del comprador autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
            'profile_complete' => $request->user()->hasCompleteProfile(),
        ]);
    }

    /**
     * PATCH /api/me/profile
     * Actualiza los datos de envío / perfil del comprador.
     * NO permite cambiar: email, password, role.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'lastname'        => 'sometimes|required|string|max:255',
            'phone'           => 'sometimes|required|string|max:30',
            'address'         => 'sometimes|required|string|max:255',
            'city'            => 'sometimes|required|string|max:255',
            'zip_code'        => 'sometimes|required|string|max:20',
            'country'         => 'sometimes|required|string|max:255',
            'document_number' => 'sometimes|nullable|string|max:30',
        ]);

        $user = $request->user();

        // Blindaje: el role nunca se cambia por este endpoint.
        unset($data['role'], $data['email'], $data['password']);

        $user->fill($data);
        $user->save();

        return response()->json([
            'message'         => 'Perfil actualizado',
            'user'            => $user->fresh(),
            'profile_complete' => $user->hasCompleteProfile(),
        ]);
    }
}
