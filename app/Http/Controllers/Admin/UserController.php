<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * GET /api/admin/users
     * Muestra el listado de usuarios paginado con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        $perPage = min($request->integer('per_page', 15), 100);
        $users = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($users);
    }

    /**
     * POST /api/admin/users
     * Crea un nuevo perfil de usuario (Comprador o Administrador).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'password'        => 'required|string|min:8',
            'role'            => ['required', Rule::in(User::ROLES)],
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:255',
            'zip_code'        => 'nullable|string|max:20',
            'country'         => 'nullable|string|max:100',
            'document_number' => 'nullable|string|max:50',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        // Audit Log
        AuditLog::create([
            'action'       => 'user.created',
            'description'  => "Perfil de usuario \"{$user->email}\" creado por administración",
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'actor_type'   => User::class,
            'actor_id'     => $request->user()?->id,
            'meta'         => ['role' => $user->role],
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Perfil creado exitosamente',
            'user'    => $user,
        ], 201);
    }

    /**
     * GET /api/admin/users/{id}
     * Devuelve el detalle del usuario con el historial de sus pedidos.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::withCount('orders')->with(['orders' => function ($q) {
            $q->latest()->limit(10);
        }])->findOrFail($id);

        return response()->json($user);
    }

    /**
     * PATCH /api/admin/users/{id}
     * Actualiza la información de un perfil de usuario.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'            => 'sometimes|required|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'email'           => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'        => 'nullable|string|min:8',
            'role'            => ['sometimes', 'required', Rule::in(User::ROLES)],
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string|max:255',
            'city'            => 'nullable|string|max:255',
            'zip_code'        => 'nullable|string|max:20',
            'country'         => 'nullable|string|max:100',
            'document_number' => 'nullable|string|max:50',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Audit Log
        AuditLog::create([
            'action'       => 'user.updated',
            'description'  => "Perfil de usuario \"{$user->email}\" actualizado",
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'actor_type'   => User::class,
            'actor_id'     => $request->user()?->id,
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * DELETE /api/admin/users/{id}
     * Elimina el perfil de un usuario (Previene auto-eliminación).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Seguridad: Un administrador no puede eliminarse a sí mismo
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'message' => 'No puedes eliminar tu propio perfil de usuario.',
            ], 422);
        }

        $userEmail = $user->email;
        $userId = $user->id;

        $user->delete();

        // Audit Log
        AuditLog::create([
            'action'       => 'user.deleted',
            'description'  => "Perfil de usuario \"{$userEmail}\" eliminado",
            'subject_type' => User::class,
            'subject_id'   => $userId,
            'actor_type'   => User::class,
            'actor_id'     => $request->user()?->id,
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Perfil de usuario eliminado exitosamente',
        ]);
    }
}
