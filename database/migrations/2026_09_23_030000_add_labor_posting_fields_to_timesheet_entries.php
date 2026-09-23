<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'posted' labor-cost status and journal traceability to timesheet
     * entries so labor cost can be posted to accounting exactly once.
     */
    public function up(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'posted'])->default('draft')->change();
            $table->foreignId('journal_id')->nullable()->after('status')->constrained('journals')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->after('journal_id')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
        });

        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->index(['status', 'journal_id']);
        });
    }

    public function down(): void
    {
        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropIndex(['status', 'journal_id']);
        });

        Schema::table('timesheet_entries', function (Blueprint $table) {
            $table->dropColumn(['posted_by', 'posted_at', 'journal_id']);
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->change();
        });
    }
};