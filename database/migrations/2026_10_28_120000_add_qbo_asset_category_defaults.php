<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->boolean('is_non_depreciable')->default(false)->after('depreciation_method');
            $table->decimal('residual_value_percent', 8, 2)->default(0)->after('is_non_depreciable');
        });
    }

    public function down(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_non_depreciable', 'residual_value_percent']);
        });
    }
};
