<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->foreignId('procurement_item_id')
                ->nullable()
                ->after('budget_line_id')
                ->constrained('procurement_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_item_id');
        });
    }
};
