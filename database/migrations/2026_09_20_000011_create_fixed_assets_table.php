<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code', 50)->unique();
            $table->string('asset_name', 160);
            $table->foreignId('asset_category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 18, 2);
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->foreignId('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('location', 150)->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('useful_life_months')->default(60);
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'none'])->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 2)->default(0);
            $table->decimal('net_book_value', 18, 2)->default(0);
            $table->enum('status', ['draft', 'capitalized', 'active', 'transferred', 'disposed'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->timestamp('capitalized_at')->nullable();
            $table->foreignId('capitalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('disposed_date')->nullable();
            $table->text('disposal_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'asset_category_id']);
            $table->index(['project_id', 'donor_id']);
        });

        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('depreciation_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('accumulated_depreciation', 18, 2);
            $table->decimal('net_book_value', 18, 2);
            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'depreciation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
