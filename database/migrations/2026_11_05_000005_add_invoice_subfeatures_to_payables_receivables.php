<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'payables-receivables')->value('id');
        if (! $parentId) return;
        $now = now();
        foreach ([
            ['slug' => 'payables-supplier-invoices', 'title' => 'Supplier Invoices (AP)', 'path' => '/procurement/supplier-invoices', 'sort_order' => 445],
            ['slug' => 'payables-customer-invoices', 'title' => 'Customer Invoices (AR)', 'path' => '/accounting/accounts-receivable', 'sort_order' => 446],
            ['slug' => 'payables-receivables-aging', 'title' => 'AP / AR & Staff Aging', 'path' => '/reports/ap-ar-aging', 'sort_order' => 447],
        ] as $menu) {
            DB::table('menus')->updateOrInsert(['slug' => $menu['slug']], $menu + ['parent_id' => $parentId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->whereIn('slug', ['payables-supplier-invoices', 'payables-customer-invoices', 'payables-receivables-aging'])->delete();
    }
};
