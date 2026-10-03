<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_workplans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->string('output_code')->nullable();
            $table->string('activity_code')->nullable();
            $table->text('activity')->nullable();
            $table->string('responsible')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('planned');
            $table->decimal('progress', 5, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('periods')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_workplans');
    }
};
