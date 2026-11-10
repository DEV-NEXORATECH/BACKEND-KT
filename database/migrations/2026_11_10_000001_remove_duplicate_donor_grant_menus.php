<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $slugs = [
            'master-grant-agreements',
            'master-donors',
            'donor-grant-grants',
            'donor-grant-donors',
            'donor-grant-integrated-record',
        ];

        $menuIds = DB::table('menus')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isNotEmpty() && DB::getSchemaBuilder()->hasTable('menu_role')) {
            DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('menus')->whereIn('id', $menuIds)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        DB::table('menus')->where('slug', 'master-donor-grant-integrated')->update([
            'title' => 'Donor & Grant Terintegrasi',
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Deliberately keep legacy duplicate menus disabled.
    }
};
