<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('chart_of_accounts', 'detail_type')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->string('detail_type', 120)->nullable()->after('account_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('chart_of_accounts', 'detail_type')) {
            Schema::table('chart_of_accounts', fn (Blueprint $table) => $table->dropColumn('detail_type'));
        }
    }
};
