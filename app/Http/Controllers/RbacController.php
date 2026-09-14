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

        // Role Access belum dikonfigurasi (role sama sekali belum punya akses menu):
        // fallback ke semua menu aktif agar sidebar tidak kosong / menu baru tidak hilang diam-diam.
        if (empty($allowedMenuIds)) {
            $allowedMenuIds = Menu::query()
                ->where('is_active', true)
                ->pluck('id')
                ->all();
        }

        $allowed = array_flip($allowedMenuIds);

        return Menu::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Menu $menu) => $this->menuVisible($menu, $allowed))
            ->map(fn (Menu $menu) => $this->formatMenuItem($menu, $allowed))
            ->values()
            ->all();
    }

    private function menuVisible(Menu $menu, array $allowed): bool
    {
        if ($menu->is_active && isset($allowed[$menu->id])) {
            return true;
        }

        return $menu->children
            ->contains(fn (Menu $child) => $child->is_active && isset($allowed[$child->id]));
    }

    private function formatMenuItem(Menu $menu, array $allowed): array
    {
        return [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'title' => $menu->title,
            'slug' => $menu->slug,
            'path' => $menu->path,
            'icon' => $menu->icon,
            'sort_order' => $menu->sort_order,
            'is_active' => $menu->is_active,
            'children' => $menu->children
                ->filter(fn (Menu $child) => $child->is_active && isset($allowed[$child->id]))
                ->values()
                ->map(fn (Menu $child) => [
                    'id' => $child->id,
                    'parent_id' => $child->parent_id,
                    'title' => $child->title,
                    'slug' => $child->slug,
                    'path' => $child->path,
                    'icon' => $child->icon,
                    'sort_order' => $child->sort_order,
                    'is_active' => $child->is_active,
                ]),
        ];
    }
}
