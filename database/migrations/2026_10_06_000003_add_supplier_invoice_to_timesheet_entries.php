<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('timesheet_entries', 'supplier_invoice_id')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->foreignId('supplier_invoice_id')
                    ->nullable()
                    ->after('journal_id')
                    ->constrained('supplier_invoices')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timesheet_entries', 'supplier_invoice_id')) {
            Schema::table('timesheet_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_invoice_id'));
        }
    }
};
