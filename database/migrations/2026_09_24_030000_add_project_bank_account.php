<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'bank_account_id')) $table->foreignId('bank_account_id')->nullable()->after('grant_agreement_id')->constrained('bank_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'bank_account_id')) $table->dropForeign(['bank_account_id']);
            if (Schema::hasColumn('projects', 'bank_account_id')) $table->dropColumn('bank_account_id');
        });
    }
};
