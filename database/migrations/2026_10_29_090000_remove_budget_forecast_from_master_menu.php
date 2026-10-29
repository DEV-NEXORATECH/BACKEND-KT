<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->whereIn('slug', [
                'master-budget-reporting', 'master-budget-categories',
                'master-reporting-categories', 'master-reporting-dimensions',
                'master-budget-lines',
            ])
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // The menu group remains intentionally disabled because Budget & Forecast
        // is now accessed from its functional module, not from Master Data.
    }
};
