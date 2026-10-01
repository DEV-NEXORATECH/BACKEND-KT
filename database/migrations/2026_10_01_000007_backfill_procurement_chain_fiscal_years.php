<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dateColumns = [
            'purchase_orders' => 'po_date',
            'goods_receipts' => 'receipt_date',
            'rfqs' => 'rfq_date',
            'supplier_contract_notifications' => 'notification_date',
            'supplier_invoices' => 'invoice_date',
        ];

        foreach ($dateColumns as $table => $dateColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fiscal_year_id') || ! Schema::hasColumn($table, $dateColumn)) {
                continue;
            }

            DB::table($table)
                ->whereNull('fiscal_year_id')
                ->whereNotNull($dateColumn)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $dateColumn) {
                    foreach ($rows as $row) {
                        $fiscalYearId = DB::table('fiscal_years')
                            ->whereDate('start_date', '<=', $row->{$dateColumn})
                            ->whereDate('end_date', '>=', $row->{$dateColumn})
                            ->value('id');

                        if ($fiscalYearId) {
                            DB::table($table)->where('id', $row->id)->update(['fiscal_year_id' => $fiscalYearId]);
                        }
                    }
                });
        }

        // For records without usable dates, inherit the fiscal year from their parent transaction.
        $parentMappings = [
            ['table' => 'purchase_orders', 'parentTable' => 'purchase_requests', 'foreignKey' => 'purchase_request_id'],
            ['table' => 'goods_receipts', 'parentTable' => 'purchase_orders', 'foreignKey' => 'purchase_order_id'],
            ['table' => 'rfqs', 'parentTable' => 'purchase_requests', 'foreignKey' => 'purchase_request_id'],
            ['table' => 'supplier_contract_notifications', 'parentTable' => 'purchase_orders', 'foreignKey' => 'purchase_order_id'],
            ['table' => 'supplier_invoices', 'parentTable' => 'purchase_orders', 'foreignKey' => 'purchase_order_id'],
        ];

        foreach ($parentMappings as $mapping) {
            if (! Schema::hasTable($mapping['table']) || ! Schema::hasTable($mapping['parentTable'])) continue;
            if (! Schema::hasColumn($mapping['table'], 'fiscal_year_id') || ! Schema::hasColumn($mapping['table'], $mapping['foreignKey'])) continue;
            if (! Schema::hasColumn($mapping['parentTable'], 'fiscal_year_id')) continue;

            DB::table($mapping['table'])
                ->whereNull('fiscal_year_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($mapping) {
                    foreach ($rows as $row) {
                        $parentFiscalYearId = DB::table($mapping['parentTable'])
                            ->where('id', $row->{$mapping['foreignKey']})
                            ->value('fiscal_year_id');

                        if ($parentFiscalYearId) {
                            DB::table($mapping['table'])
                                ->where('id', $row->id)
                                ->update(['fiscal_year_id' => $parentFiscalYearId]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Backfilled fiscal-year assignments are intentionally retained.
    }
};
