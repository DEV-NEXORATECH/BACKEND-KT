<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->decimal('actual_expense_amount', 18, 2)->default(0)->after('settled_amount');
            $table->decimal('return_amount', 18, 2)->default(0)->after('actual_expense_amount');
            $table->decimal('additional_reimbursement_amount', 18, 2)->default(0)->after('return_amount');
            $table->string('settlement_state', 40)->default('outstanding')->after('settlement_status');
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) { $table->dropColumn(['actual_expense_amount', 'return_amount', 'additional_reimbursement_amount', 'settlement_state']); });
    }
};
