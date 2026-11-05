<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $reportsId = DB::table('menus')->where('slug', 'reports')->value('id');
        $accountingId = DB::table('menus')->where('slug', 'accounting')->value('id');
        if (! $reportsId) return;
        $now = now();
        DB::table('menus')->updateOrInsert(['slug' => 'reports-general-ledger'], [
            'title' => 'General Ledger', 'path' => '/accounting/general-ledger',
            'parent_id' => $reportsId, 'sort_order' => 558, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        if ($accountingId) DB::table('menus')->where('slug', 'general-ledger')->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'reports-general-ledger')->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'general-ledger')->update(['is_active' => true, 'updated_at' => now()]);
    }
};
