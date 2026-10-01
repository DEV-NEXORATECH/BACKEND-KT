<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $dateColumns = [
        'expense_requests' => 'request_date',
        'grant_agreements' => 'start_date',
        'purchase_requests' => 'request_date',
        'fixed_assets' => 'acquisition_date',
        'journals' => 'journal_date',
        'timesheet_entries' => 'entry_date',
        'tax_transactions' => 'transaction_date',
        'payments' => 'payment_date',
        'cash_advance_returns' => 'return_date',
        'cash_advance_reimbursements' => 'paid_at',
        'bank_accounts' => 'opening_balance_date',
    ];

    public function up(): void
    {
        foreach (DB::table('fiscal_years')->get(['id', 'start_date', 'end_date']) as $year) {
            foreach ($this->dateColumns as $table => $dateColumn) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fiscal_year_id') || ! Schema::hasColumn($table, $dateColumn)) {
                    continue;
                }

                DB::table($table)
                    ->whereNull('fiscal_year_id')
                    ->whereBetween($dateColumn, [$year->start_date, $year->end_date])
                    ->update(['fiscal_year_id' => $year->id]);
            }
        }
    }

    public function down(): void
    {
        // Existing fiscal-year assignments are meaningful data. The column
        // rollback is handled by the preceding schema migration.
    }
};
