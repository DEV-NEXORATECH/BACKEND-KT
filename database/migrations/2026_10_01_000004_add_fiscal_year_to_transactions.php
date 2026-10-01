<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'expense_requests',
        'grant_agreements',
        'purchase_requests',
        'fixed_assets',
        'journals',
        'timesheet_entries',
        'bank_accounts',
        'tax_transactions',
        'payments',
        'cash_advance_returns',
        'cash_advance_reimbursements',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'fiscal_year_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('fiscal_year_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('fiscal_years')
                    ->nullOnDelete();
                $blueprint->index('fiscal_year_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'fiscal_year_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('fiscal_year_id');
            });
        }
    }
};
