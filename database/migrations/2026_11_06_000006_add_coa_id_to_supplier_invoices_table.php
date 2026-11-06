<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_invoices', 'coa_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->foreignId('coa_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete()->after('reference_number');
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('supplier_invoices', 'coa_id')) Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('coa_id'));
    }
};
