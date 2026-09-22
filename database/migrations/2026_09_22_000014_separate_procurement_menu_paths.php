<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('menus')->where('slug', 'procurement-purchase-orders')->update(['path' => '/procurement/purchase-orders', 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'procurement-goods-receipts')->update(['path' => '/procurement/goods-receipts', 'updated_at' => now()]);
        DB::table('menus')->where('slug', 'procurement-supplier-invoices')->update(['path' => '/procurement/supplier-invoices', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['procurement-purchase-orders', 'procurement-goods-receipts', 'procurement-supplier-invoices'])->update(['path' => '/procurement/purchase-requests', 'updated_at' => now()]);
    }
};
