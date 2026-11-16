<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $procurementId = DB::table('menus')->where('slug', 'master-procurement')->value('id');
        if (! $procurementId) {
            return;
        }

        DB::table('menus')->where('slug', 'master-vendors')->update([
            'parent_id' => $procurementId,
            'title' => 'Suppliers / Vendors',
            'path' => '/master-data/vendors',
            'sort_order' => 230,
            'is_active' => true,
            'updated_at' => now(),
        ]);

        $canonicalId = DB::table('menus')->where('slug', 'master-vendors')->value('id');
        DB::table('menus')
            ->where('path', '/master-data/vendors')
            ->when($canonicalId, fn ($query) => $query->where('id', '!=', $canonicalId))
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            return;
        }

        $masterDataId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if ($masterDataId) {
            DB::table('menus')->where('slug', 'master-vendors')->update([
                'parent_id' => $masterDataId,
                'sort_order' => 22,
                'updated_at' => now(),
            ]);
        }
    }
};
