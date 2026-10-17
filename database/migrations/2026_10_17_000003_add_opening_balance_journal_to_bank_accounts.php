<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_accounts', 'opening_balance_journal_id')) {
                $table->foreignId('opening_balance_journal_id')->nullable()->after('opening_balance_date')->constrained('journals')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_accounts', 'opening_balance_journal_id')) $table->dropConstrainedForeignId('opening_balance_journal_id');
        });
    }
};
