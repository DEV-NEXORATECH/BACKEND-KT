<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
        if (! $administrationId) return;

        $menuId = DB::table('menus')->where('slug', 'admin-organization-structure')->value('id');
        $values = ['title' => 'Organization Structure', 'path' => '/master-data/structure', 'parent_id' => $administrationId, 'sort_order' => 602, 'is_active' => true, 'updated_at' => now()];
        if ($menuId) {
            DB::table('menus')->where('id', $menuId)->update($values);
        } else {
            $menuId = DB::table('menus')->insertGetId($values + ['slug' => 'admin-organization-structure', 'created_at' => now()]);
        }

        $menuIds = DB::table('menus')->whereIn('slug', ['admin-organization-structure', 'admin-office-locations', 'admin-positions'])->pluck('id');
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            foreach ($menuIds as $id) DB::table('menu_role')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $id]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'admin-organization-structure')->value('id');
        if ($menuId) {
            DB::table('menu_role')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }
};
