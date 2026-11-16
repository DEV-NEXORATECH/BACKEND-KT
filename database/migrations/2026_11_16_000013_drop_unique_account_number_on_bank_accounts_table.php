<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_accounts')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                try {
                    $table->dropUnique(['account_number']);
                } catch (\Throwable $e) {
                    try {
                        $table->dropUnique('bank_accounts_account_number_unique');
                    } catch (\Throwable $e2) {
                        // Index might not exist or has already been dropped
                    }
                }
            });

            Schema::table('bank_accounts', function (Blueprint $table) {
                try {
                    $table->index('account_number');
                } catch (\Throwable $e) {
                    // Index might already exist
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_accounts')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                try {
                    $table->unique('account_number');
                } catch (\Throwable $e) {
                    // Ignored if duplicate values exist
                }
            });
        }
    }
};
