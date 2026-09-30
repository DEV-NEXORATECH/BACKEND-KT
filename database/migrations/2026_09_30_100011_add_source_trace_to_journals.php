<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('journals', function (Blueprint $table) { $table->string('source_type', 80)->nullable()->index(); $table->unsignedBigInteger('source_id')->nullable()->index(); }); }
    public function down(): void { Schema::table('journals', fn (Blueprint $table) => $table->dropColumn(['source_type','source_id'])); }
};
