<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->string('worker_type', 20)->default('internal')->after('user_id');
            $table->index(['worker_type', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropIndex(['worker_type', 'entry_date']);
            $table->dropColumn('worker_type');
        });
    }
};
