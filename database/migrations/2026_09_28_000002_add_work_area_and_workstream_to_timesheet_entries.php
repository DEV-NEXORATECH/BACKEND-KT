<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->string('work_area', 120)->nullable()->after('description');
            $table->string('workstream', 120)->nullable()->after('work_area');
            $table->index(['work_area', 'workstream']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropIndex(['work_area', 'workstream']);
            $table->dropColumn(['work_area', 'workstream']);
        });
    }
};
