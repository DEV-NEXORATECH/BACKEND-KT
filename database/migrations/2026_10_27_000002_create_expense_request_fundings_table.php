<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_request_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_request_id')->constrained('expense_requests')->cascadeOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('budget_line_id')->nullable()->constrained('budget_lines')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('currency_code', 10)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_request_fundings');
    }
};
