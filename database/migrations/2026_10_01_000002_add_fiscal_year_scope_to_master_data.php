<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'budget_lines' => 'after',
            'approval_matrices' => 'after',
            'exchange_rates' => 'after',
        ];

        foreach ($columns as $table => $_) {
            if (! Schema::hasColumn($table, 'fiscal_year_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
                    $blueprint->index('fiscal_year_id');
                });
            }
        }

        // Existing exchange rates can be scoped safely from their effective date.
        foreach (DB::table('fiscal_years')->get(['id', 'start_date', 'end_date']) as $year) {
            DB::table('exchange_rates')
                ->whereBetween('date', [$year->start_date, $year->end_date])
                ->whereNull('fiscal_year_id')
                ->update(['fiscal_year_id' => $year->id]);
        }
    }

    public function down(): void
    {
        foreach (['budget_lines', 'approval_matrices', 'exchange_rates'] as $table) {
            if (Schema::hasColumn($table, 'fiscal_year_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropConstrainedForeignId('fiscal_year_id');
                });
            }
        }
    }
};
