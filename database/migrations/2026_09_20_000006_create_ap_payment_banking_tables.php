<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('notes')->constrained('journals')->nullOnDelete();
            $table->decimal('paid_amount', 18, 2)->default(0)->after('total_amount');
            $table->timestamp('posted_at')->nullable()->after('journal_id');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 18, 2);
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['draft', 'paid', 'void'])->default('paid');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->enum('status', ['unmatched', 'matched', 'excluded', 'reconciled'])->default('matched');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('payments');
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('journal_id');
            $table->dropColumn(['paid_amount', 'posted_at']);
            $table->dropConstrainedForeignId('posted_by');
        });
    }
};
