<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $programsMenuId = DB::table('menus')->where('slug', 'master-programs-projects')->value('id');
        if (! $programsMenuId) return;

        DB::table('menus')->whereIn('slug', ['master-funding-sources', 'master-donor-types'])->update([
            'parent_id' => $programsMenuId,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        DB::table('menus')->where('slug', 'master-funding-sources')->update([
            'title' => 'Funding Sources', 'path' => '/master-data/funding-sources', 'sort_order' => 182, 'updated_at' => now(),
        ]);
        DB::table('menus')->where('slug', 'master-donor-types')->update([
            'title' => 'Donor Types', 'path' => '/master-data/donor-types', 'sort_order' => 183, 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $donorGrantMenuId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $donorGrantMenuId) return;

        DB::table('menus')->where('slug', 'master-funding-sources')->update(['parent_id' => $donorGrantMenuId, 'sort_order' => 188, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-donor-types')->update(['parent_id' => $donorGrantMenuId, 'sort_order' => 182, 'updated_at' => now()]);
    }
};
