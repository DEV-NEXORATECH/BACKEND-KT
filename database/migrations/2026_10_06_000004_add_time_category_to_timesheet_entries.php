<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('timesheet_entries', 'time_category')) {
            Schema::table('timesheet_entries', function (Blueprint $table) {
                $table->string('time_category', 40)->default('working_time')->after('workstream');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('timesheet_entries', 'time_category')) {
            Schema::table('timesheet_entries', fn (Blueprint $table) => $table->dropColumn('time_category'));
        }
    }
};
