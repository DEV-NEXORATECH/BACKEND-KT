<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_id')->constrained('taxes')->restrictOnDelete();
            $table->string('transaction_type', 40)->default('manual');
            $table->string('source_type', 120)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->date('transaction_date');
            $table->enum('direction', ['sales', 'purchase', 'withholding_in', 'withholding_out'])->default('purchase');
            $table->decimal('taxable_amount', 18, 2);
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_amount', 18, 2);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->string('e_faktur_reference', 100)->nullable();
            $table->string('e_bupot_reference', 100)->nullable();
            $table->enum('status', ['draft', 'reported', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['transaction_date', 'direction', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
    }
};
