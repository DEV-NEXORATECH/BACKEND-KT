<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $parentId) return;
        $menu = DB::table('menus')->where('slug', 'master-beneficiary-partners')->first();
        $values = [
            'parent_id' => $parentId,
            'title' => 'Implementing Partners',
            'slug' => 'master-beneficiary-partners',
            'path' => '/master-data/beneficiary-partners',
            'icon' => null,
            'sort_order' => 184,
            'is_active' => true,
            'updated_at' => now(),
        ];
        if ($menu) DB::table('menus')->where('id', $menu->id)->update($values);
        else DB::table('menus')->insert($values + ['created_at' => now()]);

        $menuId = DB::table('menus')->where('slug', 'master-beneficiary-partners')->value('id');
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            DB::table('menu_role')->insertOrIgnore(['role_id' => $roleId, 'menu_id' => $menuId]);
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'master-beneficiary-partners')->value('id');
        if ($menuId) {
            DB::table('menu_role')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }
};
