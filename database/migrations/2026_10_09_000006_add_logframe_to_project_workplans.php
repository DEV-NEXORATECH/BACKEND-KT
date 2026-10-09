<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('project_workplans', 'logframe_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->foreignId('logframe_id')->nullable()->after('project_id')->constrained('project_logframes')->nullOnDelete();
                $table->index(['project_id', 'logframe_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_workplans', 'logframe_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->dropForeign(['logframe_id']);
                $table->dropIndex(['project_id', 'logframe_id']);
                $table->dropColumn('logframe_id');
            });
        }
    }
};
