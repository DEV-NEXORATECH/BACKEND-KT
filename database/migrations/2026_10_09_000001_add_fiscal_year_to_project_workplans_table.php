<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('project_workplans', 'fiscal_year_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->foreignId('fiscal_year_id')->nullable()->after('project_id')->constrained('fiscal_years')->nullOnDelete();
                $table->index(['project_id', 'fiscal_year_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_workplans', 'fiscal_year_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('fiscal_year_id');
            });
        }
    }
};
