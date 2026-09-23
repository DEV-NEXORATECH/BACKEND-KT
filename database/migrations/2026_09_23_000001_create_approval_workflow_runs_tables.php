<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->string('module', 50);
            $table->string('approvable_type', 150);
            $table->unsignedBigInteger('approvable_id');
            $table->string('status', 20)->default('in_progress');
            $table->unsignedTinyInteger('current_level')->default(1);
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['approvable_type', 'approvable_id']);
            $table->unique(['module', 'approvable_type', 'approvable_id'], 'approval_run_target_unique');
        });

        Schema::create('approval_workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_matrix_id')->nullable()->constrained('approval_matrices')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('action_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['approval_workflow_run_id', 'level', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_actions');
        Schema::dropIfExists('approval_workflow_runs');
    }
};
