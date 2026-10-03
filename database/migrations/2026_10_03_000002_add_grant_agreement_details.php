<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->date('agreement_date')->nullable()->after('agreement_no');
            $table->string('reporting_period', 30)->nullable()->after('end_date');
            $table->string('agreement_document_path', 255)->nullable()->after('reporting_period');
        });
    }

    public function down(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->dropColumn(['agreement_date', 'reporting_period', 'agreement_document_path']);
        });
    }
};
