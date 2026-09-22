<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->foreignId('parent_expense_request_id')->nullable()->after('journal_id')->constrained('expense_requests')->nullOnDelete();
            $table->decimal('settled_amount', 18, 2)->default(0)->after('paid_amount');
            $table->enum('settlement_status', ['not_applicable', 'outstanding', 'partially_settled', 'settled'])->default('not_applicable')->after('settled_amount');
        });
    }
    public function down(): void {
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_expense_request_id');
            $table->dropColumn(['settled_amount', 'settlement_status']);
        });
    }
};
