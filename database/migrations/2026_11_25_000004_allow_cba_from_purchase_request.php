<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->dropForeign(['rfq_id']);
            $table->foreignId('rfq_id')->nullable()->change();
            $table->foreignId('purchase_request_id')->nullable()->after('rfq_id')->constrained('purchase_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_request_id');
            $table->foreignId('rfq_id')->nullable(false)->change();
        });
    }
};
