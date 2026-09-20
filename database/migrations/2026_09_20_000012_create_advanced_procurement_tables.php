<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('rfq_number', 50)->unique();
            $table->date('rfq_date');
            $table->date('submission_deadline')->nullable();
            $table->text('terms')->nullable();
            $table->enum('status', ['draft', 'issued', 'closed', 'cancelled'])->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rfq_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->enum('status', ['invited', 'responded', 'declined'])->default('invited');
            $table->timestamps();
            $table->unique(['rfq_id', 'vendor_id']);
        });

        Schema::create('vendor_quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('quotation_number', 80)->nullable();
            $table->date('quotation_date');
            $table->decimal('total_amount', 18, 2);
            $table->string('currency_code', 10)->default('IDR');
            $table->text('terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->decimal('technical_score', 8, 2)->default(0);
            $table->decimal('financial_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->enum('status', ['submitted', 'evaluated', 'selected', 'rejected'])->default('submitted');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['rfq_id', 'vendor_id']);
        });

        Schema::create('comparative_bid_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->string('cba_number', 50)->unique();
            $table->date('analysis_date');
            $table->foreignId('selected_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('selected_quotation_id')->nullable()->constrained('vendor_quotations')->nullOnDelete();
            $table->text('selection_reason')->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('rfq_id')->nullable()->after('purchase_request_id')->constrained('rfqs')->nullOnDelete();
            $table->foreignId('cba_id')->nullable()->after('rfq_id')->constrained('comparative_bid_analyses')->nullOnDelete();
            $table->string('contract_number', 80)->nullable()->after('po_number');
            $table->date('contract_date')->nullable()->after('po_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rfq_id');
            $table->dropConstrainedForeignId('cba_id');
            $table->dropColumn(['contract_number', 'contract_date']);
        });
        Schema::dropIfExists('comparative_bid_analyses');
        Schema::dropIfExists('vendor_quotations');
        Schema::dropIfExists('rfq_vendors');
        Schema::dropIfExists('rfqs');
    }
};
