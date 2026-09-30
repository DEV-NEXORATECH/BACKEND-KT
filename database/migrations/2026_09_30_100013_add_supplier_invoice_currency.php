<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('supplier_invoices', function (Blueprint $table) { $table->string('currency_code', 10)->default('IDR'); $table->decimal('exchange_rate', 18, 6)->default(1); }); }
    public function down(): void { Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropColumn(['currency_code','exchange_rate'])); }
};
