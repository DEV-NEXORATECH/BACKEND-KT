<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grant_agreements')) {
            Schema::table('grant_agreements', function (Blueprint $table) {
                if (! Schema::hasColumn('grant_agreements', 'terms_conditions')) {
                    $table->text('terms_conditions')->nullable()->after('agreement_name');
                }
                if (! Schema::hasColumn('grant_agreements', 'supporting_documents')) {
                    $table->json('supporting_documents')->nullable()->after('agreement_document_path');
                }
            });
        }
        if (Schema::hasTable('projects') && ! Schema::hasColumn('projects', 'theme')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('theme', 200)->nullable()->after('name');
            });
        }
        if (Schema::hasTable('project_workplans') && ! Schema::hasColumn('project_workplans', 'baseline')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->decimal('baseline', 18, 2)->nullable()->after('progress');
            });
        }
        if (Schema::hasTable('budget_lines') && ! Schema::hasColumn('budget_lines', 'document_path')) {
            Schema::table('budget_lines', function (Blueprint $table) {
                $table->string('document_path', 255)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        foreach (['grant_agreements' => ['terms_conditions', 'supporting_documents'], 'projects' => ['theme'], 'project_workplans' => ['baseline'], 'budget_lines' => ['document_path']] as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
                    }
                }
            }
        }
    }
};
