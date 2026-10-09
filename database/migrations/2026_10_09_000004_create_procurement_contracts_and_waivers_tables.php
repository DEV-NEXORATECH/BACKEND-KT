<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('procurement_contracts')) {
            Schema::create('procurement_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                $table->string('contract_number', 80)->unique();
                $table->string('contract_type', 50)->default('consultancy');
                $table->string('title', 255);
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('vendor_name', 200)->nullable();
                $table->string('vendor_contact', 200)->nullable();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('project_name', 200)->nullable();
                $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
                $table->string('donor_name', 200)->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->decimal('total_value', 18, 2)->default(0);
                $table->string('currency', 10)->default('IDR');
                $table->string('status', 30)->default('draft');
                $table->text('payment_terms')->nullable();
                $table->text('scope_of_work')->nullable();
                $table->text('notes')->nullable();
                $table->json('attachments')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('procurement_waivers')) {
            Schema::create('procurement_waivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
                $table->string('waiver_number', 80)->unique();
                $table->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->string('project_name', 200)->nullable();
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('vendor_name', 200)->nullable();
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->string('description', 255);
                $table->text('justification');
                $table->string('attachment_name', 255)->nullable();
                $table->string('attachment_path', 255)->nullable();
                $table->date('date');
                $table->string('status', 30)->default('pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_waivers');
        Schema::dropIfExists('procurement_contracts');
    }
};
