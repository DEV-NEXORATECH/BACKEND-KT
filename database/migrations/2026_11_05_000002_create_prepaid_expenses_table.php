<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prepaid_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('description');
            $table->date('start_date');
            $table->unsignedInteger('periods');
            $table->decimal('total_amount', 18, 2);
            $table->decimal('amortized_amount', 18, 2)->default(0);
            $table->string('status', 30)->default('active');
            $table->foreignId('prepaid_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('expense_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void { Schema::dropIfExists('prepaid_expenses'); }
};
