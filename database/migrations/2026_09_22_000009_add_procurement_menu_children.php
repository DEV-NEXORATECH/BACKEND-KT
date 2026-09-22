<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'procurement')->value('id');
        if (! $parentId) return;
        $now = now();
        foreach ([
            ['title' => 'Vendors', 'slug' => 'procurement-vendors', 'path' => '/master-data/vendors', 'sort_order' => 494],
            ['title' => 'Purchase Orders', 'slug' => 'procurement-purchase-orders', 'path' => '/procurement/purchase-requests', 'sort_order' => 495],
            ['title' => 'Goods Receipts', 'slug' => 'procurement-goods-receipts', 'path' => '/procurement/purchase-requests', 'sort_order' => 496],
            ['title' => 'Supplier Invoices', 'slug' => 'procurement-supplier-invoices', 'path' => '/procurement/purchase-requests', 'sort_order' => 497],
        ] as $menu) {
            DB::table('menus')->updateOrInsert(['slug' => $menu['slug']], [...$menu, 'parent_id' => $parentId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['procurement-vendors', 'procurement-purchase-orders', 'procurement-goods-receipts', 'procurement-supplier-invoices'])->delete();
    }
};
