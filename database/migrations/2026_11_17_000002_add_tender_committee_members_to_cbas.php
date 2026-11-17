<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->string('panelist_4_name', 120)->nullable()->after('panelist_3_position');
            $table->string('panelist_4_position', 120)->nullable()->after('panelist_4_name');
            $table->string('panelist_5_name', 120)->nullable()->after('panelist_4_position');
            $table->string('panelist_5_position', 120)->nullable()->after('panelist_5_name');
        });
    }

    public function down(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->dropColumn(['panelist_4_name', 'panelist_4_position', 'panelist_5_name', 'panelist_5_position']);
        });
    }
};
