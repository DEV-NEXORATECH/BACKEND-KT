<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('menus')->whereIn('slug', [
            'donor-grant-programs-projects',
            'donor-grant-reporting',
        ])->pluck('id');
        if ($ids->isNotEmpty() && DB::getSchemaBuilder()->hasTable('menu_role')) {
            DB::table('menu_role')->whereIn('menu_id', $ids)->delete();
        }
        DB::table('menus')->whereIn('id', $ids)->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void {}
};
