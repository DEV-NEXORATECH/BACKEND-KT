<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_transactions')) return;

        Schema::table('bank_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_transactions', 'difference')) {
                $table->decimal('difference', 18, 2)->nullable()->after('credit');
            }
            if (! Schema::hasColumn('bank_transactions', 'remarks')) {
                $table->text('remarks')->nullable()->after('difference');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bank_transactions')) return;

        Schema::table('bank_transactions', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('bank_transactions', 'difference')) $columns[] = 'difference';
            if (Schema::hasColumn('bank_transactions', 'remarks')) $columns[] = 'remarks';
            if ($columns) $table->dropColumn($columns);
        });
    }
};
