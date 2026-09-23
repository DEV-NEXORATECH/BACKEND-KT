<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'supplier_invoices',
            'customer_invoices',
            'purchase_requests',
            'purchase_orders',
            'goods_receipts',
            'journals',
            'tax_transactions',
            'fixed_assets',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'attachments')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->json('attachments')->nullable();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'supplier_invoices',
            'customer_invoices',
            'purchase_requests',
            'purchase_orders',
            'goods_receipts',
            'journals',
            'tax_transactions',
            'fixed_assets',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'attachments')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('attachments');
                });
            }
        }
    }
};
