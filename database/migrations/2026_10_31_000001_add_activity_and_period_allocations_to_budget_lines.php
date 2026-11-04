<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('budget_lines', 'activity_id')) {
                $table->foreignId('activity_id')->nullable()->after('project_id')->constrained('activities')->nullOnDelete();
            }
            if (!Schema::hasColumn('budget_lines', 'sub_output_code')) {
                $table->string('sub_output_code', 80)->nullable()->after('activity_id');
            }
            if (!Schema::hasColumn('budget_lines', 'period_allocations')) {
                $table->json('period_allocations')->nullable()->after('base_amount');
            }
        });
    }

    public function down(): void
    {
    }
};
