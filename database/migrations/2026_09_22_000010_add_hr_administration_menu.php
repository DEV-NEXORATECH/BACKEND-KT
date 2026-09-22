<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'hr-administration')->value('id');
        if (! $parentId) {
            $parentId = DB::table('menus')->insertGetId([
                'title' => 'HR & Administration', 'slug' => 'hr-administration', 'path' => '/administration', 'icon' => 'admin', 'sort_order' => 55, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $now = now();
        foreach ([
            ['title' => 'Employees', 'slug' => 'hr-employees', 'path' => '/master-data/employees', 'sort_order' => 551],
            ['title' => 'Departments', 'slug' => 'hr-departments', 'path' => '/master-data/departments', 'sort_order' => 552],
            ['title' => 'Office Locations', 'slug' => 'hr-office-locations', 'path' => '/master-data/office-locations', 'sort_order' => 553],
            ['title' => 'Role Access', 'slug' => 'hr-role-access', 'path' => '/administration/role-access', 'sort_order' => 554],
            ['title' => 'Approval Matrix', 'slug' => 'hr-approval-matrix', 'path' => '/administration/approval-matrix', 'sort_order' => 555],
            ['title' => 'Audit Log', 'slug' => 'hr-audit-log', 'path' => '/administration/audit-logs', 'sort_order' => 556],
        ] as $menu) {
            DB::table('menus')->updateOrInsert(['slug' => $menu['slug']], [...$menu, 'parent_id' => $parentId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'hr-administration')->orWhereIn('slug', ['hr-employees', 'hr-departments', 'hr-office-locations', 'hr-role-access', 'hr-approval-matrix', 'hr-audit-log'])->delete();
    }
};
