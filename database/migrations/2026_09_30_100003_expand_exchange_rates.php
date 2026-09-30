<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('exchange_rates', 'rate_type')) $table->string('rate_type', 30)->default('spot')->after('rate');
            if (! Schema::hasColumn('exchange_rates', 'reference')) $table->string('reference', 150)->nullable()->after('source');
            if (! Schema::hasColumn('exchange_rates', 'notes')) $table->text('notes')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            foreach (['notes', 'reference', 'rate_type'] as $column) if (Schema::hasColumn('exchange_rates', $column)) $table->dropColumn($column);
        });
    }
};
