<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if ($masterId) {
            DB::table('menus')->where('parent_id', $masterId)->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $masterId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if ($masterId) {
            DB::table('menus')->where('parent_id', $masterId)->update(['is_active' => true, 'updated_at' => now()]);
        }
    }
};
