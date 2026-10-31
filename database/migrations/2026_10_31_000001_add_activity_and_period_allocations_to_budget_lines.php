<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('project_id')->constrained('activities')->nullOnDelete();
            $table->string('sub_output_code', 80)->nullable()->after('activity_id');
            $table->json('period_allocations')->nullable()->after('base_amount');
            $table->index(['project_id', 'activity_id', 'sub_output_code']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'activity_id', 'sub_output_code']);
            $table->dropConstrainedForeignId('activity_id');
            $table->dropColumn(['sub_output_code', 'period_allocations']);
        });
    }
};
