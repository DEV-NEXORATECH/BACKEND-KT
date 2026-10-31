<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('project_id')->constrained('activities')->nullOnDelete();
            $table->string('activity_code', 50)->nullable()->after('activity_id');
            $table->string('activity_name', 255)->nullable()->after('activity_code');
            $table->string('sub_output_code', 50)->nullable()->after('activity_name');
            $table->string('sub_output_name', 255)->nullable()->after('sub_output_code');
            $table->string('allocation_type', 20)->default('annual')->after('proposal_period');
            $table->json('period_allocations')->nullable()->after('allocation_type');
        });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropForeign(['activity_id']);
            $table->dropColumn([
                'activity_id',
                'activity_code',
                'activity_name',
                'sub_output_code',
                'sub_output_name',
                'allocation_type',
                'period_allocations',
            ]);
        });
    }
};

