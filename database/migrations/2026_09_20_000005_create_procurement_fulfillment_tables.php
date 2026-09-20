<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('po_number', 40)->unique();
            $table->date('po_date');
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'approved', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_request_line_id')->nullable()->constrained('purchase_request_lines')->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->string('item_description', 255);
            $table->decimal('quantity', 18, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->unsignedInteger('line_order')->default(1);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('grn_number', 40)->unique();
            $table->date('receipt_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->decimal('received_quantity', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('invoice_number', 80)->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'matched', 'approved', 'posted', 'paid', 'cancelled'])->default('draft');
            $table->enum('match_status', ['unchecked', 'matched', 'partial_match', 'mismatch'])->default('unchecked');
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->string('item_description', 255);
            $table->decimal('quantity', 18, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_lines');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
