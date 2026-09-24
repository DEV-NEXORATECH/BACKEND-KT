<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_requests', 'settlement_due_date')) {
                $table->date('settlement_due_date')->nullable()->after('settlement_status');
            }
            $table->index(['expense_type', 'settlement_status', 'settlement_due_date'], 'exp_req_type_settle_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropIndex('exp_req_type_settle_due_idx');
            if (Schema::hasColumn('expense_requests', 'settlement_due_date')) {
                $table->dropColumn('settlement_due_date');
            }
        });
    }
};
