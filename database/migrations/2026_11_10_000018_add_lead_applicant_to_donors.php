<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('donors', 'lead_applicant')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->string('lead_applicant', 150)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('donors', 'lead_applicant')) {
            Schema::table('donors', fn (Blueprint $table) => $table->dropColumn('lead_applicant'));
        }
    }
};
