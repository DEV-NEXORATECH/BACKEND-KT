<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('slug', 'procurement-scn')
            ->update([
                'title' => 'Services Completion Note',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('slug', 'procurement-scn')
            ->update([
                'title' => 'Supplier Contract Notification',
                'updated_at' => now(),
            ]);
    }
};
