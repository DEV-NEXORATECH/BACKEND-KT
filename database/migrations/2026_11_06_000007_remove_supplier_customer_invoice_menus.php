<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('menus')->whereIn('slug', ['payables-supplier-invoices', 'payables-customer-invoices'])->delete();
    }

    public function down(): void
    {
        // Navigation items remain removed intentionally.
    }
};
