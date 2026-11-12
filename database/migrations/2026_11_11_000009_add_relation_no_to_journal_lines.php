<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->after('budget_line_id')->index();
        });

        // Historical dimensions are filled only by the reviewed dry-run command.
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropIndex(['relation_no']);
            $table->dropColumn('relation_no');
        });
    }
};
