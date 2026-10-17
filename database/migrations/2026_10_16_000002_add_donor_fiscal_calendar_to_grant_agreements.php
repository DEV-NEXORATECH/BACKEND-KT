<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->string('donor_fiscal_calendar', 20)->default('JAN_DEC')->after('reporting_period');
        });
    }

    public function down(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->dropColumn('donor_fiscal_calendar');
        });
    }
};
