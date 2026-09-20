<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number', 40)->unique();
            $table->string('external_request_id', 80)->nullable()->unique();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('expense_type', ['reimbursement', 'supplier_payment', 'loan', 'cash_advance', 'settlement_advance']);
            $table->date('request_date');
            $table->string('currency_code', 10)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted', 'paid'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expense_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained('expense_requests')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 18, 2);
            $table->unsignedInteger('line_order')->default(1);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('expense_request_id')->nullable()->after('supplier_invoice_id')->constrained('expense_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_request_id');
        });
        Schema::dropIfExists('expense_request_lines');
        Schema::dropIfExists('expense_requests');
    }
};
