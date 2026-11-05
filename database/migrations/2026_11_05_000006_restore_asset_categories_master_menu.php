<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) return;
        $now = now();
        $groupId = DB::table('menus')->where('slug', 'master-assets')->value('id');
        if (! $groupId) {
            $groupId = DB::table('menus')->insertGetId([
                'title' => 'Assets', 'slug' => 'master-assets', 'path' => '/master-data',
                'parent_id' => $masterId, 'sort_order' => 21, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            DB::table('menus')->where('id', $groupId)->update(['parent_id' => $masterId, 'title' => 'Assets', 'is_active' => true, 'updated_at' => $now]);
        }
        DB::table('menus')->updateOrInsert(['slug' => 'master-asset-categories'], [
            'title' => 'Asset Categories', 'path' => '/master-data/asset-categories',
            'parent_id' => $groupId, 'sort_order' => 203, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['master-assets', 'master-asset-categories'])->update(['is_active' => false, 'updated_at' => now()]);
    }
};
