<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_depreciations')) {
            Schema::table('asset_depreciations', function (Blueprint $table) {
                $table->unique(['fixed_asset_id', 'depreciation_date'], 'asset_depreciations_asset_date_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_depreciations')) {
            Schema::table('asset_depreciations', function (Blueprint $table) {
                $table->dropUnique('asset_depreciations_asset_date_unique');
            });
        }
    }
};
