<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('project_partner')) {
            Schema::create('project_partner', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('partner_id')->constrained('beneficiary_partners')->cascadeOnDelete();
                $table->string('role', 40)->default('implementing_partner');
                $table->timestamps();
                $table->unique(['project_id', 'partner_id', 'role']);
            });
        }
        if (! Schema::hasTable('project_funding_source')) {
            Schema::create('project_funding_source', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('funding_source_id')->constrained('funding_sources')->cascadeOnDelete();
                $table->decimal('amount', 18, 2)->nullable();
                $table->timestamps();
                $table->unique(['project_id', 'funding_source_id']);
            });
        }
        Schema::table('journal_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_lines', 'grant_agreement_id')) $table->foreignId('grant_agreement_id')->nullable()->after('donor_id')->constrained('grant_agreements')->nullOnDelete();
            if (! Schema::hasColumn('journal_lines', 'partner_id')) $table->foreignId('partner_id')->nullable()->after('project_id')->constrained('beneficiary_partners')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            if (Schema::hasColumn('journal_lines', 'grant_agreement_id')) $table->dropConstrainedForeignId('grant_agreement_id');
            if (Schema::hasColumn('journal_lines', 'partner_id')) $table->dropConstrainedForeignId('partner_id');
        });
        Schema::dropIfExists('project_funding_source');
        Schema::dropIfExists('project_partner');
    }
};
