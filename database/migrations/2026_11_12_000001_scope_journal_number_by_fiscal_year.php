<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique('journals_journal_number_unique');
            $table->unique(['journal_number', 'fiscal_year_id'], 'journals_number_fiscal_year_unique');
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropUnique('journals_number_fiscal_year_unique');
            $table->unique('journal_number');
        });
    }
};
