<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $menus = Menu::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Menu $menu) => $this->formatMenu($menu));

        return response()->json([
            'menus' => $menus,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $data['slug'] = $this->uniqueSlug(Str::slug($data['slug'] ?: $data['title']));
        $data['path'] = ! empty($data['path']) ? $data['path'] : '/'.$data['slug'];

        $menu = Menu::create($data);

        return response()->json([
            'message' => 'Menu berhasil dibuat.',
            'menu' => $this->formatMenu($menu->load('children')),
        ], 201);
    }

    public function update(Request $request, Menu $menu): JsonResponse
    {
        $data = $this->validatedData($request, $menu);
        $data['slug'] = $this->uniqueSlug(Str::slug($data['slug'] ?: $data['title']), $menu->id);
        $data['path'] = ! empty($data['path']) ? $data['path'] : '/'.$data['slug'];

        $menu->update($data);

        return response()->json([
            'message' => 'Menu berhasil diperbarui.',
            'menu' => $this->formatMenu($menu->load('children')),
        ]);
    }

    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json([
            'message' => 'Menu berhasil dihapus.',
        ]);
    }

    private function validatedData(Request $request, ?Menu $menu = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'title' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:140',
            ],
            'path' => ['nullable', 'string', 'max:180'],
            'icon' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $suffix = 2;

        while (Menu::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function formatMenu(Menu $menu): array
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
            'children' => $menu->children?->map(fn (Menu $child) => [
                'id' => $child->id,
                'parent_id' => $child->parent_id,
                'title' => $child->title,
                'slug' => $child->slug,
                'path' => $child->path,
                'icon' => $child->icon,
                'sort_order' => $child->sort_order,
                'is_active' => $child->is_active,
                'children' => [],
            ])->values() ?? [],
        ];
    }
}
