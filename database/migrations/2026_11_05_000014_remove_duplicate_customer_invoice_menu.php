<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Customer Invoices (AR) pointed to the exact same page as Accounts
        // Receivable, creating two visible entries for one feature.
        DB::table('menus')->where('slug', 'payables-customer-invoices')->update([
            'is_active' => true,
            'path' => '/accounting/customer-invoices',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'payables-customer-invoices')->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }
};
