<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menuIds = DB::table('menus')->whereIn('slug', [
            'expense-monitoring',
            'expense-approvals-monitoring',
        ])->pluck('id');

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
        // Keep the retired menu disabled.
    }
};
