<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')->where('slug', 'taxes-calculator')->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'tax-calculator')->update(['is_active' => true, 'parent_id' => null, 'title' => 'Tax Calculator', 'path' => '/tax-calculator', 'updated_at' => now()]);
    }

    public function down(): void {}
};
