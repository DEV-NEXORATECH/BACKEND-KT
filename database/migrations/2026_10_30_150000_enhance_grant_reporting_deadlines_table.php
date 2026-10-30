<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_reporting_deadlines', function (Blueprint $table) {
            $table->string('period_label', 100)->nullable()->after('report_type');
            $table->date('submitted_date')->nullable()->after('due_date');
            $table->string('submitted_by', 150)->nullable()->after('submitted_date');
            $table->string('document_path', 255)->nullable()->after('submitted_by');
            $table->string('document_name', 255)->nullable()->after('document_path');
            $table->unsignedInteger('reminder_days')->default(7)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('grant_reporting_deadlines', function (Blueprint $table) {
            $table->dropColumn([
                'period_label',
                'submitted_date',
                'submitted_by',
                'document_path',
                'document_name',
                'reminder_days',
            ]);
        });
    }
};

