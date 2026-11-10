<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if (! $masterId) return;

        $parent = DB::table('menus')->where('slug', 'master-membership')->first();
        if ($parent) {
            DB::table('menus')->where('id', $parent->id)->update(['parent_id' => $masterId, 'title' => 'Members / Anggota', 'sort_order' => 16, 'is_active' => true, 'updated_at' => now()]);
        } else {
            $parentId = DB::table('menus')->insertGetId(['parent_id' => $masterId, 'title' => 'Members / Anggota', 'slug' => 'master-membership', 'path' => '/master-data/customers', 'icon' => null, 'sort_order' => 16, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $parent = (object) ['id' => $parentId];
        }
        DB::table('menus')->where('slug', 'master-customers')->update(['parent_id' => $parent->id, 'sort_order' => 160, 'is_active' => true, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-membership-dues')->update(['parent_id' => $parent->id, 'sort_order' => 161, 'is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        DB::table('menus')->whereIn('slug', ['master-customers', 'master-membership-dues'])->update(['parent_id' => $masterId, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'master-membership')->update(['is_active' => false, 'updated_at' => now()]);
    }
};
