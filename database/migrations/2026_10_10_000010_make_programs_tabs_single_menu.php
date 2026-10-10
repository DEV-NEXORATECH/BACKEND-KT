<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->where('slug', 'master-programs-projects')->update([
            'path' => '/master-data/programs',
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'master-programs-projects')->update([
            'path' => '/master-data',
            'updated_at' => now(),
        ]);
    }
};
