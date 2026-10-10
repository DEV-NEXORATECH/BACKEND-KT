<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $parentId) return;

        $menu = DB::table('menus')->where('slug', 'master-funding-sources')->first();
        $legacy = DB::table('menus')->where('slug', 'master-sof')->first();

        if (! $menu && $legacy) {
            DB::table('menus')->where('id', $legacy->id)->update([
                'slug' => 'master-funding-sources',
                'title' => 'Funding Sources',
                'path' => '/master-data/funding-sources',
                'parent_id' => $parentId,
                'is_active' => true,
                'sort_order' => 188,
                'updated_at' => now(),
            ]);
            $menu = (object) ['id' => $legacy->id];
        } elseif ($menu) {
            DB::table('menus')->where('id', $menu->id)->update([
                'title' => 'Funding Sources',
                'path' => '/master-data/funding-sources',
                'parent_id' => $parentId,
                'is_active' => true,
                'sort_order' => 188,
                'updated_at' => now(),
            ]);
        } else {
            $menu = (object) ['id' => DB::table('menus')->insertGetId([
                'parent_id' => $parentId,
                'title' => 'Funding Sources',
                'slug' => 'master-funding-sources',
                'path' => '/master-data/funding-sources',
                'icon' => null,
                'sort_order' => 188,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])];
        }

        if ($legacy && $legacy->id !== $menu->id) {
            DB::table('menus')->where('id', $legacy->id)->update(['is_active' => false, 'updated_at' => now()]);
        }

        $roleIds = DB::table('roles')->pluck('id');
        foreach ($roleIds as $roleId) {
            DB::table('menu_role')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $menu->id]);
        }
    }

    public function down(): void
    {
        $menu = DB::table('menus')->where('slug', 'master-funding-sources')->first();
        if ($menu) {
            DB::table('menu_role')->where('menu_id', $menu->id)->delete();
            DB::table('menus')->where('id', $menu->id)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }
};
