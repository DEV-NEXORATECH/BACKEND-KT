<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE bank_accounts MODIFY account_number VARCHAR(50) NOT NULL");
        }
    }

    public function down(): void
    {
        // Account numbers may contain non-numeric values (for example
        // XXX-CIMB), so reverting to a numeric column would be lossy.
    }
};
