<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_waivers', function (Blueprint $table) {
            $table->date('prepared_date')->nullable()->after('prepared_title');
            $table->date('authorised_date')->nullable()->after('authorised_title');
            $table->date('verified_date')->nullable()->after('verified_title');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_waivers', function (Blueprint $table) {
            $table->dropColumn(['prepared_date', 'authorised_date', 'verified_date']);
        });
    }
};
