<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $expenseParent = DB::table('menus')->where('slug', 'expenses-approvals')->value('id');
        if (! $expenseParent) return;
        $now = now();
        DB::table('menus')->where('slug', 'payables-receivables-aging')->update(['is_active' => false, 'updated_at' => $now]);
        DB::table('menus')->updateOrInsert(['slug' => 'expense-staff-aging'], [
            'title' => 'Staff Aging', 'path' => '/reports/aging', 'parent_id' => $expenseParent,
            'sort_order' => 39, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'expense-staff-aging')->update(['is_active' => false, 'updated_at' => now()]);
    }
};
