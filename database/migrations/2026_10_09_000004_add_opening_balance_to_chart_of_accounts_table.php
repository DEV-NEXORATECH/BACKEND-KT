<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->decimal('opening_balance', 18, 2)->default(0)->after('normal_balance');
                $table->date('opening_balance_date')->nullable()->after('opening_balance');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chart_of_accounts', 'opening_balance')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->dropColumn(['opening_balance', 'opening_balance_date']);
            });
        }
    }
};
