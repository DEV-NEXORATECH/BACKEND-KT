<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('original_amount', 18, 2)->nullable();
            $table->decimal('converted_amount', 18, 2)->nullable();
            $table->decimal('fx_gain_loss', 18, 2)->nullable();
            $table->string('rate_source', 50)->nullable();
            $table->date('rate_date')->nullable();
        });
    }
    public function down(): void { Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['exchange_rate','original_amount','converted_amount','fx_gain_loss','rate_source','rate_date'])); }
};
