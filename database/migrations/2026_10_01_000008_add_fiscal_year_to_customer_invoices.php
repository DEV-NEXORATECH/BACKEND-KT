<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_invoices') && ! Schema::hasColumn('customer_invoices', 'fiscal_year_id')) {
            Schema::table('customer_invoices', function (Blueprint $table) {
                $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
                $table->index('fiscal_year_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_invoices') && Schema::hasColumn('customer_invoices', 'fiscal_year_id')) {
            Schema::table('customer_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('fiscal_year_id'));
        }
    }
};
