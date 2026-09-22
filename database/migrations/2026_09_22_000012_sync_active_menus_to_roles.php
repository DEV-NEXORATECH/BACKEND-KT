<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roleIds = DB::table('roles')->pluck('id');
        $menuIds = DB::table('menus')->where('is_active', true)->pluck('id');
        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($menuIds as $menuId) {
                $rows[] = ['role_id' => $roleId, 'menu_id' => $menuId];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('menu_role')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Existing role menu assignments are intentionally preserved on rollback.
    }
};
