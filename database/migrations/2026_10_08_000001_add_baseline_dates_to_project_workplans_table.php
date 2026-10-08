<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_workplans', function (Blueprint $table) {
            $table->date('baseline_start_date')->nullable()->after('end_date');
            $table->date('baseline_end_date')->nullable()->after('baseline_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('project_workplans', function (Blueprint $table) {
            $table->dropColumn(['baseline_start_date', 'baseline_end_date']);
        });
    }
};
