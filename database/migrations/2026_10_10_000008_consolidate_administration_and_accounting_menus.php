<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hiddenSlugs = ['master-organization-structure', 'master-chart-of-accounts'];
        $menuIds = DB::table('menus')->whereIn('slug', $hiddenSlugs)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
            DB::table('menus')->whereIn('id', $menuIds)->update(['is_active' => false, 'updated_at' => now()]);
        }

        $administrationId = DB::table('menus')->where('slug', 'administration')->value('id');
        if (! $administrationId) return;

        foreach ([
            ['slug' => 'admin-office-locations', 'title' => 'Office Locations', 'path' => '/administration/office-locations', 'sort_order' => 604],
            ['slug' => 'admin-positions', 'title' => 'Positions', 'path' => '/administration/positions', 'sort_order' => 605],
        ] as $item) {
            $existing = DB::table('menus')->where('slug', $item['slug'])->first();
            $values = $item + ['parent_id' => $administrationId, 'is_active' => true, 'updated_at' => now()];
            if ($existing) DB::table('menus')->where('id', $existing->id)->update($values);
            else DB::table('menus')->insert($values + ['created_at' => now()]);
        }

        $roleIds = DB::table('roles')->pluck('id');
        $menuIds = DB::table('menus')->whereIn('slug', ['admin-office-locations', 'admin-positions'])->pluck('id');
        foreach ($roleIds as $roleId) foreach ($menuIds as $menuId) {
            DB::table('menu_role')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $menuId]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['master-organization-structure', 'master-chart-of-accounts'])->update(['is_active' => true, 'updated_at' => now()]);
    }
};
