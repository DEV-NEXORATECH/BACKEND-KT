<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('budget_name', 150)->nullable()->after('relation_no');
            $table->index(['fiscal_year_id', 'budget_name']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['fiscal_year_id', 'budget_name']);
            $table->dropColumn('budget_name');
        });
    }
};
