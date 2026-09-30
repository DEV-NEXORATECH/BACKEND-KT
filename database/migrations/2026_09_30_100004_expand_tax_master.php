<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            if (! Schema::hasColumn('taxes', 'organization_id')) $table->foreignId('organization_id')->nullable()->after('id')->constrained('organizations')->nullOnDelete();
            if (! Schema::hasColumn('taxes', 'effective_start_date')) $table->date('effective_start_date')->nullable()->after('rate_percent');
            if (! Schema::hasColumn('taxes', 'effective_end_date')) $table->date('effective_end_date')->nullable()->after('effective_start_date');
            if (! Schema::hasColumn('taxes', 'applicable_rule')) $table->string('applicable_rule', 150)->nullable()->after('effective_end_date');
        });

        // Kaoem Telapak does not collect VAT. Keep historical tax rows intact,
        // but hide PPN from active tax configuration and transaction options.
        DB::table('taxes')->where(function ($query) {
            $query->whereRaw('UPPER(tax_type) IN (?, ?)', ['PPN', 'VAT'])->orWhereRaw('UPPER(tax_type) LIKE ?', ['%PPN%'])->orWhereRaw('UPPER(tax_type) LIKE ?', ['%VAT%']);
        })->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            if (Schema::hasColumn('taxes', 'organization_id')) $table->dropConstrainedForeignId('organization_id');
            foreach (['applicable_rule', 'effective_end_date', 'effective_start_date'] as $column) if (Schema::hasColumn('taxes', $column)) $table->dropColumn($column);
        });
    }
};
