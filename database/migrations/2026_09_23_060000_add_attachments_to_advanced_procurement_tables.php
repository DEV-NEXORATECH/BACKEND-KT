<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['rfqs', 'comparative_bid_analyses', 'supplier_contract_notifications'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'attachments')) Schema::table($table, fn (Blueprint $t) => $t->json('attachments')->nullable());
        }
    }
    public function down(): void
    {
        foreach (['rfqs', 'comparative_bid_analyses', 'supplier_contract_notifications'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'attachments')) Schema::table($table, fn (Blueprint $t) => $t->dropColumn('attachments'));
        }
    }
};
