<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('timesheet_entries', 'task_type')) {
                $table->string('task_type', 30)->default('project_activity')->after('activity_id');
            }
            if (! Schema::hasColumn('timesheet_entries', 'rate_scheme')) {
                $table->string('rate_scheme', 30)->nullable()->after('is_billable');
            }
            if (! Schema::hasColumn('timesheet_entries', 'applied_rate')) {
                $table->decimal('applied_rate', 18, 2)->nullable()->after('rate_scheme');
            }
            if (! Schema::hasColumn('timesheet_entries', 'billable_hours')) {
                $table->decimal('billable_hours', 8, 2)->nullable()->after('applied_rate');
            }
            if (! Schema::hasColumn('timesheet_entries', 'calculated_amount')) {
                $table->decimal('calculated_amount', 18, 2)->nullable()->after('billable_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['task_type', 'rate_scheme', 'applied_rate', 'billable_hours', 'calculated_amount'] as $col) {
                if (Schema::hasColumn('timesheet_entries', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
