<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE expense_requests MODIFY status ENUM('draft','submitted','verified','approved','rejected','posted','partially_paid','paid','fully_paid') NOT NULL DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE expense_requests SET status = 'paid' WHERE status IN ('partially_paid','fully_paid')");
            DB::statement("ALTER TABLE expense_requests MODIFY status ENUM('draft','submitted','verified','approved','rejected','posted','paid') NOT NULL DEFAULT 'draft'");
        }
    }
};
