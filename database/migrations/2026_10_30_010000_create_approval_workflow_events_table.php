<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approval_workflow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_action_id')->nullable()->constrained('approval_workflow_actions')->nullOnDelete();
            $table->string('event', 40);
            $table->unsignedTinyInteger('from_level')->nullable();
            $table->unsignedTinyInteger('to_level')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_position')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['approval_workflow_run_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_events');
    }
};
