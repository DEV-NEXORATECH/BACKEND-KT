<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->enum('proposal_period', ['monthly', 'quarterly', 'semester', 'annual'])->default('annual')->after('description');
            $table->foreignId('currency_id')->nullable()->after('proposal_period')->constrained('currencies')->nullOnDelete();
            $table->decimal('exchange_rate', 18, 6)->default(1)->after('currency_id');
            $table->decimal('base_amount', 18, 2)->nullable()->after('total_amount');
            $table->index(['project_id', 'proposal_period']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'proposal_period']);
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['proposal_period', 'exchange_rate', 'base_amount']);
        });
    }
};
