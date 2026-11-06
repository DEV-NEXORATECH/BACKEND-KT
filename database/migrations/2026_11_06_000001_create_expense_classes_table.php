<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->enum('detail_type', ['sub_output', 'workstream', 'cost_category']);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['donor_id', 'code']);
        });
    }

    public function down(): void { Schema::dropIfExists('expense_classes'); }
};
