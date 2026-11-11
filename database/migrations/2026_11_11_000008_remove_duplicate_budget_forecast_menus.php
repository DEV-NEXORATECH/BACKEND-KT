<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateSlugs = [
            'budget-forecast-planning',
            'budget-forecast-monitoring',
            'budget-forecast-forecast',
        ];

        $menuIds = DB::table('menus')->whereIn('slug', $duplicateSlugs)->pluck('id');

        if ($menuIds->isNotEmpty() && DB::getSchemaBuilder()->hasTable('menu_role')) {
            DB::table('menu_role')->whereIn('menu_id', $menuIds)->delete();
        }

        DB::table('menus')->whereIn('id', $menuIds)->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', [
            'budget-forecast-planning',
            'budget-forecast-monitoring',
            'budget-forecast-forecast',
        ])->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};
