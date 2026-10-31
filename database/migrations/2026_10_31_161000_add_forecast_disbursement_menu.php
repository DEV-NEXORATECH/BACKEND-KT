<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menus')->where('slug', 'donor-grant')->first();
        if ($parent) {
            $existing = DB::table('menus')->where('slug', 'donor-grant-forecast-disbursement')->first();
            if (!$existing) {
                // Find order of donor-grant-budget
                $budgetMenu = DB::table('menus')->where('slug', 'donor-grant-budget')->first();
                $sortOrder = $budgetMenu ? $budgetMenu->sort_order + 1 : 50;

                DB::table('menus')->insert([
                    'parent_id' => $parent->id,
                    'slug' => 'donor-grant-forecast-disbursement',
                    'title' => 'Forecast & Disbursement',
                    'path' => '/donor-grant/forecast-disbursement',
                    'icon' => 'trending-up',
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'donor-grant-forecast-disbursement')->delete();
    }
};

