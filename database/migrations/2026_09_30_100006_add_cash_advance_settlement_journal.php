<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_requests', 'settlement_journal_id')) {
                $table->foreignId('settlement_journal_id')->nullable()->after('journal_id')->constrained('journals')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (Schema::hasColumn('expense_requests', 'settlement_journal_id')) $table->dropConstrainedForeignId('settlement_journal_id');
        });
    }
};
