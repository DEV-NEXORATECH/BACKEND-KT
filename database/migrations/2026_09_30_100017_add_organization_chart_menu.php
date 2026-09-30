<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'master-organization-structure')->value('id');
        if (! $parentId) return;
        DB::table('menus')->updateOrInsert(
            ['slug' => 'master-organization-chart'],
            [
                'title' => 'Organization Chart',
                'path' => '/master-data/structure',
                'parent_id' => $parentId,
                'sort_order' => 160,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'master-organization-chart')->delete();
    }
};
