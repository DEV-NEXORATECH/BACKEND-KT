<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleMenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()
                ->with([
                    'menus' => fn ($query) => $query->orderBy('sort_order'),
                    'permissions' => fn ($query) => $query->orderBy('slug'),
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'menu_ids' => $role->menus->pluck('id')->values(),
                    'permission_ids' => $role->permissions->pluck('id')->values(),
                ]),
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function permissionOptions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::query()
                ->orderBy('slug')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function sync(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'menu_ids' => ['present', 'array'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ]);

        $role->menus()->sync($data['menu_ids']);

        return response()->json([
            'message' => 'Akses menu role berhasil diperbarui.',
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($data['permission_ids']);

        return response()->json([
            'message' => 'Permission role berhasil diperbarui.',
        ]);
    }
}
