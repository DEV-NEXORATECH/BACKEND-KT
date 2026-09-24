<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('expense_requests', 'settlement_due_date')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                $table->date('settlement_due_date')->nullable()->after('settlement_status');
                $table->index(['expense_type', 'settlement_status', 'settlement_due_date']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('expense_requests', 'settlement_due_date')) {
            Schema::table('expense_requests', function (Blueprint $table) {
                $table->dropIndex(['expense_type', 'settlement_status', 'settlement_due_date']);
                $table->dropColumn('settlement_due_date');
            });
        }
    }
};
