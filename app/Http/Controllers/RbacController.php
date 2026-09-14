<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RbacController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing([
            'role.permissions',
            'role.menus' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ]);

        return response()->json([
            'role' => $this->formatRole($user),
            'permissions' => $this->formatPermissions($user),
            'menus' => $this->formatMenus($user),
        ]);
    }

    private function formatRole($user): ?array
    {
        if (! $user->role) {
            return null;
        }

        return [
            'id' => $user->role->id,
            'name' => $user->role->name,
            'slug' => $user->role->slug,
        ];
    }

    private function formatPermissions($user): array
    {
        return $user->role?->permissions
            ->map(fn ($permission) => [
                'name' => $permission->name,
                'slug' => $permission->slug,
            ])
            ->values()
            ->all() ?? [];
    }

    private function formatMenus($user): array
    {
        $allowedMenuIds = $user->role?->menus->pluck('id')->all() ?? [];

        return Menu::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->whereIn('id', $allowedMenuIds)
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('id', $allowedMenuIds)
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Menu $menu) => [
                'title' => $menu->title,
                'slug' => $menu->slug,
                'path' => $menu->path,
                'icon' => $menu->icon,
                'children' => $menu->children->map(fn (Menu $child) => [
                    'title' => $child->title,
                    'slug' => $child->slug,
                    'path' => $child->path,
                    'icon' => $child->icon,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
