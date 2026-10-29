<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expense_request_lines', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->nullable()->after('description');
            $table->string('unit', 40)->nullable()->after('quantity');
            $table->decimal('frequency', 12, 4)->nullable()->after('unit');
            $table->decimal('unit_price', 18, 2)->nullable()->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('expense_request_lines', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit', 'frequency', 'unit_price']);
        });
    }
};
