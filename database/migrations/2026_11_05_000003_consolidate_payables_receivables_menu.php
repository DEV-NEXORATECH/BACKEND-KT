<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $accountingId = DB::table('menus')->where('slug', 'accounting')->value('id');
        if (! $accountingId) return;
        $now = now();
        $parentId = DB::table('menus')->where('slug', 'payables-receivables')->value('id');
        if (! $parentId) {
            $parentId = DB::table('menus')->insertGetId([
                'title' => 'Payables & Receivables', 'slug' => 'payables-receivables',
                'path' => '/accounting/payables-receivables', 'sort_order' => 44,
                'parent_id' => $accountingId, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            DB::table('menus')->where('id', $parentId)->update(['parent_id' => $accountingId, 'title' => 'Payables & Receivables', 'path' => '/accounting/payables-receivables', 'is_active' => true, 'updated_at' => $now]);
        }
        $children = [
            ['slug' => 'accounts-payable', 'title' => 'Accounts Payable', 'path' => '/accounting/accounts-payable', 'sort_order' => 441],
            ['slug' => 'accounts-receivable', 'title' => 'Accounts Receivable', 'path' => '/accounting/accounts-receivable', 'sort_order' => 442],
            ['slug' => 'accounts-payable-workflow', 'title' => 'AP Workflow', 'path' => '/accounting/ap-workflow', 'sort_order' => 443],
            ['slug' => 'accounts-receivable-workflow', 'title' => 'AR Workflow', 'path' => '/accounting/ar-workflow', 'sort_order' => 444],
            ['slug' => 'payables-receivables-aging', 'title' => 'AP / AR & Staff Aging', 'path' => '/reports/ap-ar-aging', 'sort_order' => 445],
        ];
        foreach ($children as $child) {
            DB::table('menus')->updateOrInsert(['slug' => $child['slug']], $child + ['parent_id' => $parentId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'payables-receivables')->delete();
    }
};
