<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_transactions') || Schema::hasColumn('bank_transactions', 'fiscal_year_id')) {
            return;
        }

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
            $table->index('fiscal_year_id');
        });

        if (Schema::hasTable('fiscal_years') && Schema::hasColumn('bank_transactions', 'transaction_date')) {
            $years = \Illuminate\Support\Facades\DB::table('fiscal_years')->get(['id', 'start_date', 'end_date']);
            foreach ($years as $year) {
                \Illuminate\Support\Facades\DB::table('bank_transactions')
                    ->whereNull('fiscal_year_id')
                    ->whereBetween('transaction_date', [$year->start_date, $year->end_date])
                    ->update(['fiscal_year_id' => $year->id]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_transactions') && Schema::hasColumn('bank_transactions', 'fiscal_year_id')) {
            Schema::table('bank_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('fiscal_year_id'));
        }
    }
};
