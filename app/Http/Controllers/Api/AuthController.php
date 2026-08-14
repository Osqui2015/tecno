<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => ['required', 'string', 'min:8', 'max:30', 'regex:'.self::phoneArRule()],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'phone.required' => 'El número de celular es obligatorio.',
            'phone.regex'    => 'El formato del celular no es válido. Ejemplos válidos: 1141234567, 11 4123-4567, +54 9 11 4123-4567, 5491141234567.',
        ]);

        // Rechazar el formato "15 XXXX-XXXX" sin código de área (no se puede normalizar
        // a un número válido en Argentina: el único código de 2 dígitos es 11).
        if (preg_match('/^15\s\d/', $data['phone'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => ['Si tu número empieza con 15, incluí el código de área. Ej: 11 15 4123-4567 o 11 4123-4567.'],
            ]);
        }

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => self::normalizePhoneAr($data['phone']),
            'password' => Hash::make($data['password']),
            'role'     => User::ROLE_COMPRADOR,
        ]);
        $user->assignRole(User::ROLE_COMPRADOR);
        $user->load('roles');

        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            return response()->json([
                'message'      => 'Login requiere 2FA',
                'requires_2fa' => true,
            ]);
        }

        $user->load('roles');
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user'    => $this->formatUser($user),
            'token'   => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('roles');

        return response()->json($this->formatUser($user));
    }

    /**
     * Helper: formatea el user con role + roles para el frontend.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role'  => $user->role, // accessor que toma el role principal de Spatie
            'roles' => $user->roles->pluck('name'),
        ];
    }

    /**
     * Regla regex para validar números de celular argentinos.
     * Acepta:
     *  - 10 dígitos locales (cód. área de 2-4 dígitos + número)
     *  - Con +54 / 549 (formato internacional)
     *  - Con o sin el 9 (prefijo celular AR moderno)
     *  - Con separadores (espacios, guiones)
     *
     * NO acepta:
     *  - El formato viejo "15 XXXX-XXXX" sin código de área (es ambiguo)
     *  - Códigos de área que empiecen con 15
     *  - Códigos de 2 dígitos distintos de 11
     */
    public static function phoneArRule(): string
    {
        // Códigos válidos:
        //   - 2 dígitos: solo "11" (CABA / GBA)
        //   - 3 dígitos: 2XX, 3XX (todas las provincias, ej: 221, 351, 261)
        //   - 4 dígitos: 2XXX, 3XXX (interior, ej: 2202, 2942)
        // NO aceptamos: 2 dígitos distintos de 11, ni nada que empiece con 15.
        return '/^(?:\+?54[-\s]?)?(?:9[-\s]?)?(?:11|2\d{2}|3\d{2}|2\d{3}|3\d{3})[-\s]?(\d{3,4})[-\s]?(\d{3,4})$/';
    }

    /**
     * Normaliza un celular AR a un formato canónico de solo dígitos
     * con prefijo 549 (formato E.164 argentino para celular).
     *
     *  - "1141234567"     → "5491141234567"
     *  - "11 4123-4567"   → "5491141234567"
     *  - "+54 9 11 41234567" → "5491141234567"
     *  - "5491141234567"  → "5491141234567"
     */
    public static function normalizePhoneAr(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        // Quitar prefijo país si está
        if (str_starts_with($digits, '54')) {
            $digits = substr($digits, 2);
        }
        // Quitar 9 (prefijo celular) si quedó
        if (str_starts_with($digits, '9') && strlen($digits) >= 11) {
            $digits = substr($digits, 1);
        }

        // Anteponer 549 (formato E.164 AR para celulares)
        return '549' . $digits;
    }
}
