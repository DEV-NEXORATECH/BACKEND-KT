<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('fixed_assets', 'capitalization_journal_id')) $table->foreignId('capitalization_journal_id')->nullable()->after('journal_id')->constrained('journals')->nullOnDelete();
            if (! Schema::hasColumn('fixed_assets', 'disposal_journal_id')) $table->foreignId('disposal_journal_id')->nullable()->after('capitalization_journal_id')->constrained('journals')->nullOnDelete();
            if (! Schema::hasColumn('fixed_assets', 'impairment_journal_id')) $table->foreignId('impairment_journal_id')->nullable()->after('disposal_journal_id')->constrained('journals')->nullOnDelete();
            if (! Schema::hasColumn('fixed_assets', 'in_service_date')) $table->date('in_service_date')->nullable()->after('acquisition_date');
            if (! Schema::hasColumn('fixed_assets', 'residual_value')) $table->decimal('residual_value', 18, 2)->default(0)->after('acquisition_cost');
            if (! Schema::hasColumn('fixed_assets', 'impairment_amount')) $table->decimal('impairment_amount', 18, 2)->default(0)->after('accumulated_depreciation');
            if (! Schema::hasColumn('fixed_assets', 'disposal_type')) $table->string('disposal_type', 30)->nullable()->after('disposed_date');
            if (! Schema::hasColumn('fixed_assets', 'disposal_proceeds')) $table->decimal('disposal_proceeds', 18, 2)->default(0)->after('disposal_type');
            if (! Schema::hasColumn('fixed_assets', 'disposal_bank_account_id')) $table->foreignId('disposal_bank_account_id')->nullable()->after('disposal_proceeds')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            foreach (['disposal_bank_account_id', 'impairment_journal_id', 'disposal_journal_id', 'capitalization_journal_id'] as $column) {
                if (Schema::hasColumn('fixed_assets', $column)) $table->dropForeign([$column]);
            }
            foreach (['capitalization_journal_id', 'disposal_journal_id', 'impairment_journal_id', 'in_service_date', 'residual_value', 'impairment_amount', 'disposal_type', 'disposal_proceeds', 'disposal_bank_account_id'] as $column) {
                if (Schema::hasColumn('fixed_assets', $column)) $table->dropColumn($column);
            }
        });
    }
};
