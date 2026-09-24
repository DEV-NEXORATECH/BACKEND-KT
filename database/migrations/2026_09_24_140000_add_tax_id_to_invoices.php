<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_invoices') && ! Schema::hasColumn('supplier_invoices', 'tax_id')) {
            Schema::table('supplier_invoices', function (Blueprint $table) {
                $table->foreignId('tax_id')->nullable()->after('vendor_id')->constrained('taxes')->nullOnDelete();
            });
        }

        if (Schema::hasTable('customer_invoices') && ! Schema::hasColumn('customer_invoices', 'tax_id')) {
            Schema::table('customer_invoices', function (Blueprint $table) {
                $table->foreignId('tax_id')->nullable()->after('program_id')->constrained('taxes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_invoices') && Schema::hasColumn('supplier_invoices', 'tax_id')) {
            Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('tax_id'));
        }
        if (Schema::hasTable('customer_invoices') && Schema::hasColumn('customer_invoices', 'tax_id')) {
            Schema::table('customer_invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('tax_id'));
        }
    }
};
