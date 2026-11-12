<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuIds = DB::table('menus')
            ->where('slug', 'master-chart-of-accounts')
            ->pluck('id');

        if ($menuIds->isEmpty()) {
            return;
        }

        if (DB::getSchemaBuilder()->hasTable('menu_role')) {
            DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('menus')->whereIn('id', $menuIds)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('slug', 'master-chart-of-accounts')
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};

