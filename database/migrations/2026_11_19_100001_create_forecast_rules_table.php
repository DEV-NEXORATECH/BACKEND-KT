<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecast_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years')->nullOnDelete();
            $table->foreignId('grant_agreement_id')->nullable()->constrained('grant_agreements')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('relation_no', 100)->nullable();
            $table->string('cost_category', 255);
            $table->enum('direction', ['increase', 'decrease'])->default('increase');
            $table->enum('mode', ['percentage', 'idr'])->default('percentage');
            $table->decimal('value', 18, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['relation_no', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_rules');
    }
};
