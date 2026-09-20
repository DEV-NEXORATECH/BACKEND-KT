<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('currency_code', 10)->default('IDR');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->enum('status', ['draft', 'posted', 'partially_received', 'received', 'void'])->default('draft');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('received_amount', 18, 2)->default(0);
            $table->text('description')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->cascadeOnDelete();
            $table->foreignId('revenue_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2);
            $table->unsignedInteger('line_order')->default(1);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('customer_invoice_id')->nullable()->after('expense_request_id')->constrained('customer_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_invoice_id');
        });
        Schema::dropIfExists('customer_invoice_lines');
        Schema::dropIfExists('customer_invoices');
        Schema::dropIfExists('customers');
    }
};
