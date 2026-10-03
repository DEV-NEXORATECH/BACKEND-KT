<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_sources', function (Blueprint $table) {
            $table->foreignId('donor_id')->nullable()->after('funding_type')->constrained('donors')->nullOnDelete();
            $table->string('funding_intermediary', 150)->nullable()->after('donor_id');
            $table->foreignId('currency_id')->nullable()->after('funding_intermediary')->constrained('currencies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funding_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('donor_id');
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn('funding_intermediary');
        });
    }
};
