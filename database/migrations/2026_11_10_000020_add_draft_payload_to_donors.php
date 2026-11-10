<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('donors', 'draft_payload')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->json('draft_payload')->nullable()->after('lead_applicant');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('donors', 'draft_payload')) {
            Schema::table('donors', fn (Blueprint $table) => $table->dropColumn('draft_payload'));
        }
    }
};
