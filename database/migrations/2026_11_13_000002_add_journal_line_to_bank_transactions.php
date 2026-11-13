<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bank_transactions', 'journal_line_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->foreignId('journal_line_id')
                    ->nullable()
                    ->after('journal_id')
                    ->constrained('journal_lines')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_transactions', 'journal_line_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('journal_line_id');
            });
        }
    }
};
