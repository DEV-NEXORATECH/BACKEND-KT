<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuIds = DB::table('menus')
            ->where('slug', 'master-procurement')
            ->orWhereIn('parent_id', function ($query) {
                $query->select('id')->from('menus')->where('slug', 'master-procurement');
            })
            ->pluck('id');

        if ($menuIds->isEmpty()) return;

        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'master-procurement')->update(['is_active' => true, 'updated_at' => now()]);
        $parentId = DB::table('menus')->where('slug', 'master-procurement')->value('id');
        if ($parentId) {
            DB::table('menus')->where('parent_id', $parentId)->update(['is_active' => true, 'updated_at' => now()]);
        }
    }
};
