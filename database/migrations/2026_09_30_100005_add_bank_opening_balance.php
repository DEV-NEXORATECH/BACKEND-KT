<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_accounts', 'opening_balance')) $table->decimal('opening_balance', 18, 2)->default(0)->after('gl_account_id');
            if (! Schema::hasColumn('bank_accounts', 'opening_balance_date')) $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            foreach (['opening_balance_date', 'opening_balance'] as $column) if (Schema::hasColumn('bank_accounts', $column)) $table->dropColumn($column);
        });
    }
};
