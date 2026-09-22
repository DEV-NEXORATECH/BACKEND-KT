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

        $allMenus = Menu::query()->where('is_active', true)->orderBy('sort_order')->get();
        $byParent = $allMenus->groupBy('parent_id');
        return $byParent->get(null, collect())
            ->filter(fn (Menu $menu) => $this->menuVisible($menu, $allowed, $byParent))
            ->map(fn (Menu $menu) => $this->formatMenuItem($menu, $allowed, $byParent))
            ->values()
            ->all();
    }

    private function menuVisible(Menu $menu, array $allowed, $byParent): bool
    {
        return isset($allowed[$menu->id]) || $byParent->get($menu->id, collect())->contains(fn (Menu $child) => $this->menuVisible($child, $allowed, $byParent));
    }

    private function formatMenuItem(Menu $menu, array $allowed, $byParent): array
    {
        $parentAllowed = isset($allowed[$menu->id]);
        return [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'title' => $menu->title,
            'slug' => $menu->slug,
            'path' => $menu->path,
            'icon' => $menu->icon,
            'sort_order' => $menu->sort_order,
            'is_active' => $menu->is_active,
            'children' => $byParent->get($menu->id, collect())
                ->filter(fn (Menu $child) => $parentAllowed || $this->menuVisible($child, $allowed, $byParent))
                ->values()
                ->map(fn (Menu $child) => $this->formatMenuItem($child, $allowed, $byParent))
                ->all(),
        ];
    }
}
