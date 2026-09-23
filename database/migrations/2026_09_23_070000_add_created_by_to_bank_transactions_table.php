<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bank_transactions') && ! Schema::hasColumn('bank_transactions', 'created_by')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('payment_id')->constrained('users')->nullOnDelete();
                $table->index('created_by');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bank_transactions') && Schema::hasColumn('bank_transactions', 'created_by')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropIndex(['created_by']);
                $table->dropColumn('created_by');
            });
        }
    }
};
