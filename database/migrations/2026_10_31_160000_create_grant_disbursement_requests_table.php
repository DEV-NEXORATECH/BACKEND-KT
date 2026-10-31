<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grant_disbursement_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fiscal_year_id')->nullable()->index();
            $table->unsignedBigInteger('grant_agreement_id')->index();
            $table->unsignedBigInteger('bank_account_id')->nullable()->index();
            $table->string('request_no', 64)->unique();
            $table->string('period_type', 32)->default('quarterly'); // quarterly, monthly, tranche
            $table->string('period_label', 64); // e.g. Q1 2026, Tranche 1
            $table->date('request_date');
            $table->unsignedBigInteger('currency_id')->nullable()->index();
            $table->decimal('forecast_total_amount', 16, 2)->default(0);
            $table->decimal('requested_amount', 16, 2)->default(0);
            $table->decimal('contract_fx_rate', 12, 4)->default(1.0000);
            $table->decimal('requested_amount_idr', 18, 2)->default(0);
            $table->string('status', 32)->default('draft'); // draft, submitted, approved, received, rejected
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();

            // Realisasi Penerimaan Dana (Funds Received with Actual FX Rate)
            $table->date('received_date')->nullable();
            $table->decimal('received_amount', 16, 2)->default(0);
            $table->decimal('actual_fx_rate', 12, 4)->nullable();
            $table->decimal('received_amount_idr', 18, 2)->default(0);
            $table->decimal('fx_gain_loss', 18, 2)->default(0);
            $table->unsignedBigInteger('received_bank_account_id')->nullable()->index();
            $table->string('receipt_proof_path')->nullable();
            $table->text('received_notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('grant_agreement_id')->references('id')->on('grant_agreements')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            $table->foreign('received_bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });

        Schema::create('grant_forecast_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disbursement_request_id')->nullable()->index();
            $table->unsignedBigInteger('grant_agreement_id')->index();
            $table->unsignedBigInteger('budget_line_id')->nullable()->index();
            $table->string('period_label', 64); // e.g. Q1 2026
            $table->string('sub_output_code', 64)->nullable();
            $table->string('sub_output_name')->nullable();
            $table->string('activity_code', 64)->nullable();
            $table->string('activity_name')->nullable();
            $table->string('cost_category')->nullable(); // COA / Category
            $table->decimal('proposal_budget', 16, 2)->default(0);
            $table->decimal('historical_expense', 16, 2)->default(0);
            $table->string('adjustment_type', 32)->default('percentage'); // percentage, idr_amount, fixed
            $table->decimal('adjustment_percent', 8, 2)->default(0);
            $table->decimal('adjustment_idr', 16, 2)->default(0);
            $table->decimal('forecast_amount', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('disbursement_request_id')->references('id')->on('grant_disbursement_requests')->onDelete('cascade');
            $table->foreign('grant_agreement_id')->references('id')->on('grant_agreements')->onDelete('cascade');
            $table->foreign('budget_line_id')->references('id')->on('budget_lines')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_forecast_items');
        Schema::dropIfExists('grant_disbursement_requests');
    }
};

