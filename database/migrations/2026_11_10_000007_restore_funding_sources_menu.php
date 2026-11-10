<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'master-funding-projects')->value('id');
        if (! $parentId) return;

        DB::table('menus')->updateOrInsert(
            ['slug' => 'master-funding-sources'],
            [
                'parent_id' => $parentId,
                'title' => 'Funding Sources',
                'path' => '/master-data/funding-sources',
                'sort_order' => 184,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void {}
};
