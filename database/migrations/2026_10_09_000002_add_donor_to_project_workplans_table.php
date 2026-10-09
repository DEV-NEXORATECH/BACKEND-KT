<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('project_workplans', 'donor_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->foreignId('donor_id')->nullable()->after('project_id')->constrained('donors')->nullOnDelete();
                $table->index(['donor_id', 'project_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_workplans', 'donor_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->dropForeign(['donor_id']);
                $table->dropIndex(['donor_id', 'project_id']);
                $table->dropColumn('donor_id');
            });
        }
    }
};
