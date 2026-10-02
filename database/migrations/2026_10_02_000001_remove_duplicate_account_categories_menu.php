<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('menus')->where('slug', 'master-account-categories')->value('id');
        if ($menuId) {
            DB::table('menu_role')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }

    public function down(): void
    {
        $parentId = DB::table('menus')->where('slug', 'master-finance-accounting')->value('id');
        if ($parentId && ! DB::table('menus')->where('slug', 'master-account-categories')->exists()) {
            $id = DB::table('menus')->insertGetId([
                'parent_id' => $parentId,
                'title' => 'Account Categories',
                'slug' => 'master-account-categories',
                'path' => '/master-data/account-categories',
                'sort_order' => 176,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roleIds = DB::table('roles')->pluck('id');
            foreach ($roleIds as $roleId) DB::table('menu_role')->insertOrIgnore(['menu_id' => $id, 'role_id' => $roleId]);
        }
    }
};
