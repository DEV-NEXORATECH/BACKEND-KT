<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->string('billing_mode', 20)->nullable()->after('worker_type');
            $table->decimal('fee_total', 15, 2)->nullable()->after('billing_mode');
            $table->decimal('work_days', 8, 2)->nullable()->after('fee_total');
            $table->decimal('rate_per_day', 15, 2)->nullable()->after('work_days');
            $table->decimal('rate_per_hour', 15, 2)->nullable()->after('rate_per_day');
            $table->decimal('break_hours', 6, 2)->nullable()->after('rate_per_hour');
            $table->decimal('normal_hours', 6, 2)->nullable()->after('break_hours');
            $table->decimal('payable_amount', 15, 2)->nullable()->after('normal_hours');
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropColumn(['billing_mode', 'fee_total', 'work_days', 'rate_per_day', 'rate_per_hour', 'break_hours', 'normal_hours', 'payable_amount']);
        });
    }
};
