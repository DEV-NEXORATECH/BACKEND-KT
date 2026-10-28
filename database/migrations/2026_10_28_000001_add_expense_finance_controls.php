<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->enum('payment_type', ['Transfer', 'Petty Cash'])->default('Transfer')->after('expense_type');
            $table->date('activity_start_date')->nullable()->after('request_date');
            $table->date('activity_end_date')->nullable()->after('activity_start_date');
            $table->string('voucher_number', 40)->nullable()->unique()->after('request_number');
            $table->boolean('category_mismatch_flag')->default(false)->after('decision_notes');
            $table->text('category_mismatch_reason')->nullable()->after('category_mismatch_flag');
            $table->text('settlement_due_override_reason')->nullable()->after('settlement_due_date');
            $table->foreignId('settlement_due_overridden_by')->nullable()->after('settlement_due_override_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('settlement_due_overridden_at')->nullable()->after('settlement_due_overridden_by');
            $table->foreignId('loan_receivable_journal_id')->nullable()->after('journal_id')->constrained('journals')->nullOnDelete();
        });

        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('cost_type', 80)->nullable()->after('description');
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('cost_type', 80)->nullable()->after('name');
        });

        Schema::create('holiday_calendars', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name', 160);
            $table->enum('type', ['national_holiday', 'cuti_bersama']);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_calendars');
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('cost_type'));
        Schema::table('budget_lines', fn (Blueprint $table) => $table->dropColumn('cost_type'));
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropForeign(['settlement_due_overridden_by']);
            $table->dropForeign(['loan_receivable_journal_id']);
            $table->dropUnique(['voucher_number']);
            $table->dropColumn(['payment_type', 'activity_start_date', 'activity_end_date', 'voucher_number', 'category_mismatch_flag', 'category_mismatch_reason', 'settlement_due_override_reason', 'settlement_due_overridden_by', 'settlement_due_overridden_at', 'loan_receivable_journal_id']);
        });
    }
};
