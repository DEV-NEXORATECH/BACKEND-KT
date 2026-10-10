<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groupId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $groupId) return;

        DB::table('menus')->where('id', $groupId)->update([
            'path' => '/master-data/donor-grant-integrated',
            'is_active' => true,
            'updated_at' => now(),
        ]);

        $childId = DB::table('menus')->where('slug', 'master-donor-grant-integrated')->value('id');
        if ($childId) {
            DB::table('menu_role')->where('menu_id', $childId)->delete();
            DB::table('menus')->where('id', $childId)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $groupId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $groupId) return;

        DB::table('menus')->where('id', $groupId)->update(['path' => '/master-data', 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-donor-grant-integrated')->update(['is_active' => true, 'updated_at' => now()]);
    }
};
