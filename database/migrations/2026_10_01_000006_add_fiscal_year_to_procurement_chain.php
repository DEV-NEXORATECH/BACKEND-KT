<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['purchase_orders', 'goods_receipts', 'rfqs', 'supplier_contract_notifications', 'supplier_invoices'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'fiscal_year_id')) continue;
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
                $blueprint->index('fiscal_year_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['purchase_orders', 'goods_receipts', 'rfqs', 'supplier_contract_notifications', 'supplier_invoices'] as $table) {
            if (Schema::hasColumn($table, 'fiscal_year_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('fiscal_year_id'));
            }
        }
    }
};
