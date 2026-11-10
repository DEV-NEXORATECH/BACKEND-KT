<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) return;

        DB::table('menus')->where('slug', 'master-finance-accounting')->update(['sort_order' => 18, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-funding-projects')->update(['sort_order' => 19, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-budget-reporting')->update(['sort_order' => 20, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-expenses')->update(['sort_order' => 21, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-assets')->update(['sort_order' => 22, 'updated_at' => now()]);

        DB::table('menus')->where('slug', 'master-customers')->update(['parent_id' => $masterId, 'sort_order' => 16, 'is_active' => true, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-membership-dues')->update(['parent_id' => $masterId, 'sort_order' => 17, 'is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $financeId = DB::table('menus')->where('slug', 'master-finance-accounting')->value('id');
        if (! $financeId) return;
        DB::table('menus')->where('slug', 'master-customers')->update(['parent_id' => $financeId, 'sort_order' => 178, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-membership-dues')->update(['parent_id' => $financeId, 'sort_order' => 181, 'updated_at' => now()]);
    }
};
