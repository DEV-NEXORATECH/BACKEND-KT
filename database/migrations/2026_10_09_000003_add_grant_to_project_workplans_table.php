<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('project_workplans', 'grant_agreement_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->foreignId('grant_agreement_id')->nullable()->after('donor_id')->constrained('grant_agreements')->nullOnDelete();
                $table->index(['grant_agreement_id', 'project_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_workplans', 'grant_agreement_id')) {
            Schema::table('project_workplans', function (Blueprint $table) {
                $table->dropForeign(['grant_agreement_id']);
                $table->dropIndex(['grant_agreement_id', 'project_id']);
                $table->dropColumn('grant_agreement_id');
            });
        }
    }
};
