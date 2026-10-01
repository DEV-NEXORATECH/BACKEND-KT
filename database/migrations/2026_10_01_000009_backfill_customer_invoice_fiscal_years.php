<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_invoices') || ! Schema::hasColumn('customer_invoices', 'fiscal_year_id')) return;

        DB::table('customer_invoices')
            ->whereNull('fiscal_year_id')
            ->whereNotNull('invoice_date')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $fiscalYearId = DB::table('fiscal_years')
                        ->whereDate('start_date', '<=', $row->invoice_date)
                        ->whereDate('end_date', '>=', $row->invoice_date)
                        ->value('id');
                    if ($fiscalYearId) {
                        DB::table('customer_invoices')->where('id', $row->id)->update(['fiscal_year_id' => $fiscalYearId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Preserve historical fiscal-year assignments.
    }
};
