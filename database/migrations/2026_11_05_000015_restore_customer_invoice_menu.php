<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'payables-receivables')->value('id');
        if (! $parentId) return;

        $now = now();
        DB::table('menus')->updateOrInsert(
            ['slug' => 'payables-customer-invoices'],
            [
                'title' => 'Customer Invoices (AR)',
                'path' => '/accounting/customer-invoices',
                'parent_id' => $parentId,
                'sort_order' => 446,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('menus')->where('slug', 'payables-customer-invoices')->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }
};
