<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'signature_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('signature_path')->nullable()->after('bank_account_holder');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'signature_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('signature_path');
            });
        }
    }
};
