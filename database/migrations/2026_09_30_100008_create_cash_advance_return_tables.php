<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_advance_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('return_amount', 18, 2);
            $table->string('payment_method', 30)->default('bank_transfer');
            $table->date('return_date');
            $table->string('reference_no', 100)->nullable();
            $table->string('status', 30)->default('received');
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('cash_advance_reimbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('budget_line_id')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('pending');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::table('bank_transactions', function (Blueprint $table) { $table->foreignId('cash_advance_return_id')->nullable()->after('payment_id')->constrained('cash_advance_returns')->nullOnDelete(); });
    }
    public function down(): void { Schema::table('bank_transactions', function (Blueprint $table) { $table->dropForeign(['cash_advance_return_id']); $table->dropColumn('cash_advance_return_id'); }); Schema::dropIfExists('cash_advance_reimbursements'); Schema::dropIfExists('cash_advance_returns'); }
};
