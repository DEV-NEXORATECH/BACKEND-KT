<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('journal_id')->nullable()->after('payment_id')->constrained('journals')->nullOnDelete();
            $table->index(['journal_id', 'status']);
        });

        DB::statement(<<<'SQL'
            UPDATE bank_transactions
            SET journal_id = (
                SELECT payments.journal_id
                FROM payments
                WHERE payments.id = bank_transactions.payment_id
            )
            WHERE payment_id IS NOT NULL AND journal_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE bank_transactions
            SET journal_id = (
                SELECT cash_advance_returns.journal_id
                FROM cash_advance_returns
                WHERE cash_advance_returns.id = bank_transactions.cash_advance_return_id
            )
            WHERE cash_advance_return_id IS NOT NULL AND journal_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['journal_id', 'status']);
            $table->dropConstrainedForeignId('journal_id');
        });
    }
};
