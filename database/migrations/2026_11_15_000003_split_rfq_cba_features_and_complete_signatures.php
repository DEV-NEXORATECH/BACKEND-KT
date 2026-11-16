<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('checked_by_title', 120)->nullable()->after('checked_by');
        });

        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->string('prepared_by_position', 120)->nullable()->after('prepared_by');
            $table->string('budget_holder_position', 120)->nullable()->after('budget_holder_name');
            $table->string('panelist_1_position', 120)->nullable()->after('panelist_1_name');
            $table->string('panelist_2_position', 120)->nullable()->after('panelist_2_name');
            $table->string('panelist_3_position', 120)->nullable()->after('panelist_3_name');
        });

        $parentId = DB::table('menus')->where('slug', 'procurement')->value('id');
        $legacy = DB::table('menus')->where('slug', 'procurement-rfq-cba')->first();
        if ($parentId) {
            $now = now();
            DB::table('menus')->updateOrInsert(
                ['slug' => 'procurement-rfq'],
                ['title' => 'Request for Quotation', 'path' => '/procurement/rfq', 'parent_id' => $parentId, 'sort_order' => 493, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
            DB::table('menus')->updateOrInsert(
                ['slug' => 'procurement-cba'],
                ['title' => 'Competitive Bid Analysis', 'path' => '/procurement/cba', 'parent_id' => $parentId, 'sort_order' => 494, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );

            if ($legacy) {
                $roleIds = DB::table('menu_role')->where('menu_id', $legacy->id)->pluck('role_id');
                $newMenuIds = DB::table('menus')->whereIn('slug', ['procurement-rfq', 'procurement-cba'])->pluck('id');
                foreach ($newMenuIds as $menuId) {
                    foreach ($roleIds as $roleId) {
                        DB::table('menu_role')->insertOrIgnore(['menu_id' => $menuId, 'role_id' => $roleId]);
                    }
                }
                DB::table('menus')->where('id', $legacy->id)->update(['is_active' => false, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $menuIds = DB::table('menus')->whereIn('slug', ['procurement-rfq', 'procurement-cba'])->pluck('id');
        DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->delete();
        DB::table('menus')->where('slug', 'procurement-rfq-cba')->update(['is_active' => true, 'updated_at' => now()]);

        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->dropColumn(['prepared_by_position', 'budget_holder_position', 'panelist_1_position', 'panelist_2_position', 'panelist_3_position']);
        });
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropColumn('checked_by_title');
        });
    }
};
