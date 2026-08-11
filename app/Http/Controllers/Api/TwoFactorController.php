<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    /**
     * GET /api/me/two-factor
     * Devuelve el estado actual del 2FA.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled'    => $user->two_factor_confirmed_at !== null,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    /**
     * POST /api/me/two-factor/setup
     * Genera un secret + QR + recovery codes.
     * NO confirma todavía; el user debe verificar un código primero.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        // Generar secret
        $secret = $google2fa->generateSecretKey();

        // Generar recovery codes (8 códigos de un solo uso)
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }

        // Guardar (NO confirmado todavía)
        $user->two_factor_secret = Crypt::encryptString($secret);
        $user->two_factor_recovery_codes = Crypt::encryptString(json_encode($recoveryCodes));
        $user->save();

        // Generar QR inline (svg)
        $appName = config('app.name');
        $qrCode = $google2fa->getQRCodeUrl($appName, $user->email, $secret);

        return response()->json([
            'secret'          => $secret,
            'qr_url'          => $qrCode,
            'recovery_codes'  => $recoveryCodes,
        ]);
    }

    /**
     * POST /api/me/two-factor/verify
     * Body: { code: "123456" }
     * Verifica un código y, si es válido, marca 2FA como confirmado.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Primero generá el secret con /setup'], 422);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $google2fa = new Google2FA();

        $valid = $google2fa->verifyKey($secret, $data['code']);

        if (! $valid) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => '2FA activado',
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    /**
     * DELETE /api/me/two-factor
     * Desactiva 2FA.
     */
    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json(['message' => '2FA desactivado']);
    }

    /**
     * POST /api/auth/2fa-challenge
     * (No autenticado) Verifica código 2FA usando email + código.
     * Body: { email, code }
     * Usado durante el login si el user tiene 2FA activado.
     */
    public function challenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string',
        ]);

        $user = \App\Models\User::where('email', $data['email'])->first();

        if (! $user || ! $user->two_factor_confirmed_at || ! $user->two_factor_secret) {
            return response()->json(['message' => 'Usuario sin 2FA activo'], 404);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $google2fa = new Google2FA();

        // Acepta código TOTP O un recovery code
        $validCode = $google2fa->verifyKey($secret, $data['code']);
        $usedRecovery = null;

        if (! $validCode) {
            // Probar recovery codes
            $recoveryCodes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes ?? ''), true) ?? [];
            $usedRecovery = in_array(strtoupper($data['code']), array_map('strtoupper', $recoveryCodes), true);

            if ($usedRecovery) {
                // Quitar el código usado
                $recoveryCodes = array_filter($recoveryCodes, fn ($c) => strtoupper($c) !== strtoupper($data['code']));
                $user->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($recoveryCodes)));
                $user->save();
            }
        }

        if (! $validCode && ! $usedRecovery) {
            return response()->json(['message' => 'Código inválido'], 422);
        }

        // Crear token Sanctum
        $token = $user->createToken('web')->plainTextToken;
        $user->load('roles');

        return response()->json([
            'message' => 'Login con 2FA exitoso',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
                'roles' => $user->roles->pluck('name'),
            ],
            'token'   => $token,
        ]);
    }
}
