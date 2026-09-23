<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('amendment_number', 30);
            $table->unsignedSmallInteger('version')->default(1);
            $table->json('changes')->nullable()->comment('Snapshot of changed fields: old vs new values');
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['purchase_order_id', 'version']);
        });

        Schema::create('vendor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->unsignedTinyInteger('quality_score')->default(0)->comment('1-5 scale');
            $table->unsignedTinyInteger('delivery_score')->default(0)->comment('1-5 scale');
            $table->unsignedTinyInteger('price_score')->default(0)->comment('1-5 scale');
            $table->unsignedTinyInteger('service_score')->default(0)->comment('1-5 scale');
            $table->decimal('overall_score', 3, 1)->default(0)->comment('Weighted average');
            $table->text('evaluator_notes')->nullable();
            $table->unsignedBigInteger('evaluated_by')->nullable();
            $table->timestamps();
            $table->index(['vendor_id', 'overall_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_evaluations');
        Schema::dropIfExists('po_amendments');
    }
};
