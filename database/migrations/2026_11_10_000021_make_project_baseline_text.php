<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_logframes')) {
            Schema::table('project_logframes', function (Blueprint $table) {
                $table->text('baseline')->nullable()->change();
                $table->text('target')->nullable()->change();
                $table->text('actual')->nullable()->change();
            });
        }
        if (Schema::hasTable('project_workplans') && Schema::hasColumn('project_workplans', 'baseline')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->text('baseline')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_logframes')) {
            Schema::table('project_logframes', function (Blueprint $table) {
                $table->decimal('baseline', 18, 2)->nullable()->change();
                $table->decimal('target', 18, 2)->nullable()->change();
                $table->decimal('actual', 18, 2)->nullable()->change();
            });
        }
        if (Schema::hasTable('project_workplans') && Schema::hasColumn('project_workplans', 'baseline')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->decimal('baseline', 18, 2)->nullable()->change();
            });
        }
    }
};
