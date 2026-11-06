<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::table('expense_requests', function ($table) {
            $table->foreignId('finance_acknowledged_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->timestamp('finance_acknowledged_at')->nullable()->after('finance_acknowledged_by');
        });
        if (DB::getDriverName() === 'mysql') DB::statement("ALTER TABLE expense_requests MODIFY status ENUM('draft','submitted','verified','approved','ready_for_payment','rejected','posted','partially_paid','paid','fully_paid') NOT NULL DEFAULT 'draft'");
    }
    public function down(): void {
        Schema::table('expense_requests', function ($table) { $table->dropConstrainedForeignId('finance_acknowledged_by'); $table->dropColumn('finance_acknowledged_at'); });
        if (DB::getDriverName() === 'mysql') DB::statement("ALTER TABLE expense_requests MODIFY status ENUM('draft','submitted','verified','approved','rejected','posted','partially_paid','paid','fully_paid') NOT NULL DEFAULT 'draft'");
    }
};
