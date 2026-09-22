<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'hr-administration')->value('id');
        if (! $parentId) return;
        $now = now();
        DB::table('menus')->updateOrInsert(['slug' => 'hr-master-menu'], ['title' => 'Master Menu', 'path' => '/administration/master-menu', 'sort_order' => 557, 'parent_id' => $parentId, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]);
        DB::table('menus')->where('slug', 'administration')->update(['is_active' => false, 'updated_at' => $now]);
        DB::table('menus')->whereIn('slug', ['master-menu', 'role-access', 'administration-approval-matrix', 'audit-log'])->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'administration')->update(['is_active' => true]);
        DB::table('menus')->whereIn('slug', ['master-menu', 'role-access', 'administration-approval-matrix', 'audit-log'])->update(['is_active' => true]);
        DB::table('menus')->where('slug', 'hr-master-menu')->delete();
    }
};
