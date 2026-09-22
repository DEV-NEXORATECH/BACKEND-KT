<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->foreignId('donor_id')->nullable()->after('requester_id')->constrained('donors')->nullOnDelete();
            $table->foreignId('grant_agreement_id')->nullable()->after('donor_id')->constrained('grant_agreements')->nullOnDelete();
            $table->foreignId('program_id')->nullable()->after('grant_agreement_id')->constrained('programs')->nullOnDelete();
            $table->foreignId('funding_source_id')->nullable()->after('program_id')->constrained('funding_sources')->nullOnDelete();
            $table->foreignId('document_type_id')->nullable()->after('funding_source_id')->constrained('document_types')->nullOnDelete();
            $table->foreignId('tax_id')->nullable()->after('document_type_id')->constrained('taxes')->nullOnDelete();
            $table->json('attachments')->nullable()->after('decision_notes');
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donor_id');
            $table->dropConstrainedForeignId('grant_agreement_id');
            $table->dropConstrainedForeignId('program_id');
            $table->dropConstrainedForeignId('funding_source_id');
            $table->dropConstrainedForeignId('document_type_id');
            $table->dropConstrainedForeignId('tax_id');
            $table->dropColumn('attachments');
        });
    }
};
