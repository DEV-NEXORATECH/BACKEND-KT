<?php

namespace App\Services\Rbac;

use App\Models\Menu;
use App\Models\User;

class RbacPayloadBuilder
{
    public function build(User $user): array
    {
        $user->loadMissing([
            'role.permissions',
            'role.menus' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order'),
        ]);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'role' => $this->formatRole($user),
            'roles' => $user->role ? [$this->formatRole($user)] : [],
            'permissions' => $this->formatPermissions($user),
            'menus' => $this->formatMenus($user),
        ];
    }

    private function formatRole(User $user): ?array
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

    private function formatPermissions(User $user): array
    {
        return $user->role?->permissions
            ->sortBy('slug')
            ->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
            ])
            ->values()
            ->all() ?? [];
    }

    private function formatMenus(User $user): array
    {
        $allowed = array_flip($user->role?->menus->pluck('id')->all() ?? []);

        return Menu::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Menu $menu) => $this->menuVisible($menu, $allowed))
            ->map(fn (Menu $menu) => $this->formatMenuItem($menu, $allowed))
            ->values()
            ->all();
    }

    private function menuVisible(Menu $menu, array $allowed): bool
    {
        return isset($allowed[$menu->id])
            || $menu->children->contains(fn (Menu $child) => isset($allowed[$child->id]));
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
                ->filter(fn (Menu $child) => isset($allowed[$child->id]))
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
                    'children' => [],
                ])
                ->all(),
        ];
    }
}
