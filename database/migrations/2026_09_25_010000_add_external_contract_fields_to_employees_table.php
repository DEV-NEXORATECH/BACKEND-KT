<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'employment_type')) {
                $table->string('employment_type', 20)->default('internal')->after('position');
            }
            if (! Schema::hasColumn('employees', 'contract_total_fee')) {
                $table->decimal('contract_total_fee', 18, 2)->nullable()->after('employment_type');
            }
            if (! Schema::hasColumn('employees', 'contract_total_days')) {
                $table->unsignedInteger('contract_total_days')->nullable()->after('contract_total_fee');
            }
            if (! Schema::hasColumn('employees', 'daily_cost_rate')) {
                $table->decimal('daily_cost_rate', 18, 2)->nullable()->after('contract_total_days');
            }
            if (! Schema::hasColumn('employees', 'default_rate_scheme')) {
                $table->string('default_rate_scheme', 30)->default('daily_capped_8h')->after('hourly_cost_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['employment_type', 'contract_total_fee', 'contract_total_days', 'daily_cost_rate', 'default_rate_scheme'] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
