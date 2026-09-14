<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleMenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()
                ->with(['menus' => fn ($query) => $query->orderBy('sort_order')])
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'menu_ids' => $role->menus->pluck('id')->values(),
                ]),
        ]);
    }

    public function sync(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'menu_ids' => ['required', 'array'],
            'menu_ids.*' => ['integer', 'exists:menus,id'],
        ]);

        $role->menus()->sync($data['menu_ids']);

        return response()->json([
            'message' => 'Akses menu role berhasil diperbarui.',
        ]);
    }
}
