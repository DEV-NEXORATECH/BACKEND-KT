<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['journals', 'expense_requests', 'customer_invoices', 'supplier_invoices'] as $tableName) {
            if (! Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'functional_currency_code')) $table->string('functional_currency_code', 10)->nullable();
                if (! Schema::hasColumn($tableName, 'original_amount')) $table->decimal('original_amount', 18, 2)->nullable();
                if (! Schema::hasColumn($tableName, 'converted_amount')) $table->decimal('converted_amount', 18, 2)->nullable();
                if (! Schema::hasColumn($tableName, 'rate_source')) $table->string('rate_source', 50)->nullable();
                if (! Schema::hasColumn($tableName, 'rate_date')) $table->date('rate_date')->nullable();
                if (! Schema::hasColumn($tableName, 'fx_gain_loss')) $table->decimal('fx_gain_loss', 18, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['journals', 'expense_requests', 'customer_invoices', 'supplier_invoices'] as $tableName) {
            if (! Schema::hasTable($tableName)) continue;
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = ['functional_currency_code', 'original_amount', 'converted_amount', 'rate_source', 'rate_date', 'fx_gain_loss'];
                foreach ($columns as $column) if (Schema::hasColumn($tableName, $column)) $table->dropColumn($column);
            });
        }
    }
};
