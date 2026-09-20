<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_line_id')->constrained('budget_lines')->cascadeOnDelete();
            $table->string('source_type', 80);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->decimal('amount', 18, 2);
            $table->enum('status', ['open', 'released', 'converted', 'cancelled'])->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['budget_line_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_commitments');
    }
};
