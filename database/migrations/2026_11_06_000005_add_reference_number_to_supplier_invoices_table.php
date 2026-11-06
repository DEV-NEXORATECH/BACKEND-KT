<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_invoices', 'reference_number')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->string('reference_number', 100)->nullable()->after('invoice_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('supplier_invoices', 'reference_number')) {
            Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropColumn('reference_number'));
        }
    }
};
