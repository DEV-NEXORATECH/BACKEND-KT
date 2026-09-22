<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['sometimes', 'in:web,mobile'],
            'action' => ['sometimes', 'string', 'max:40'],
            'module' => ['sometimes', 'string', 'max:80'],
            'search' => ['sometimes', 'string', 'max:160'],
        ]);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage < 1 || $perPage > 100 ? 20 : $perPage;

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('platform'), fn ($query) => $query->where('platform', $request->query('platform')))
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->query('module')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->query('action')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->query('entity_type')))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->query('entity_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('entity_id', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Audit log berhasil dimuat.',
            'data' => $logs->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'module' => $log->module,
                'platform' => $log->platform ?: 'web',
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'previous_values' => $log->previous_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
