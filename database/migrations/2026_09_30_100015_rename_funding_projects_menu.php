<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('menus')
            ->where('slug', 'master-funding-projects')
            ->update(['title' => 'Donor & Grant', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('slug', 'master-funding-projects')
            ->update(['title' => 'Funding & Projects', 'updated_at' => now()]);
    }
};
