<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $accountingId = DB::table('menus')->where('slug', 'accounting')->value('id');

        // Asset Categories remains available as master data through its endpoint,
        // but it must not be shown as a separate Assets submenu anymore.
        DB::table('menus')->whereIn('slug', [
            'master-assets',
            'master-asset-categories',
        ])->update([
            'is_active' => false,
            'updated_at' => $now,
        ]);

        if (! $accountingId) {
            return;
        }

        // Fixed Assets is one direct Accounting menu item, not a nested submenu.
        DB::table('menus')->where('slug', 'fixed-assets')->update([
            'parent_id' => $accountingId,
            'title' => 'Fixed Asset',
            'path' => '/accounting/fixed-assets',
            'sort_order' => 48,
            'is_active' => true,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'master-assets')->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
        DB::table('menus')->where('slug', 'master-asset-categories')->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};
