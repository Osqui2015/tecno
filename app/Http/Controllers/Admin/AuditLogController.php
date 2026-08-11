<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-logs
     * Lista de cambios recientes, con filtros básicos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('actor');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', $request->integer('actor_id'));
        }

        $perPage = min($request->integer('per_page', 50), 100);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($logs);
    }
}
