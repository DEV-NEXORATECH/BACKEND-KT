<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taxes') && ! Schema::hasColumn('taxes', 'fiscal_year_id')) {
            Schema::table('taxes', function (Blueprint $table) {
                $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
                $table->index('fiscal_year_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('taxes') && Schema::hasColumn('taxes', 'fiscal_year_id')) {
            Schema::table('taxes', fn (Blueprint $table) => $table->dropConstrainedForeignId('fiscal_year_id'));
        }
    }
};
