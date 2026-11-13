<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepaid_expenses', function (Blueprint $table) {
            $table->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('grant_agreement_id')->nullable()->after('project_id')->constrained('grant_agreements')->nullOnDelete();
            $table->foreignId('donor_id')->nullable()->after('grant_agreement_id')->constrained('donors')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('donor_id')->constrained('programs')->nullOnDelete();
            $table->string('relation_no', 100)->nullable()->after('program_id')->index();
            $table->foreignId('budget_line_id')->nullable()->after('relation_no')->constrained('budget_lines')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->after('budget_line_id')->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('recognition_journal_id')->nullable()->after('bank_account_id')->constrained('journals')->nullOnDelete();
        });

        Schema::create('prepaid_expense_amortizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepaid_expense_id')->constrained('prepaid_expenses')->cascadeOnDelete();
            $table->unsignedInteger('period');
            $table->date('amortization_date');
            $table->decimal('amount', 18, 2);
            $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['prepaid_expense_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_expense_amortizations');
        Schema::table('prepaid_expenses', function (Blueprint $table) {
            foreach (['fiscal_year_id', 'grant_agreement_id', 'donor_id', 'program_id', 'budget_line_id', 'bank_account_id', 'recognition_journal_id'] as $column) {
                $table->dropForeign([$column]);
            }
            $table->dropIndex(['relation_no']);
            $table->dropColumn(['fiscal_year_id', 'grant_agreement_id', 'donor_id', 'program_id', 'relation_no', 'budget_line_id', 'bank_account_id', 'recognition_journal_id']);
        });
    }
};
