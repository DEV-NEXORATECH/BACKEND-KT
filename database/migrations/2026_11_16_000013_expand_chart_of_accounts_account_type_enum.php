<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('chart_of_accounts')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->enum('account_type', [
                    'asset', 'liability', 'equity', 'revenue', 'expense',
                    'other_income', 'other_expense',
                ])->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chart_of_accounts')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->change();
            });
        }
    }
};
