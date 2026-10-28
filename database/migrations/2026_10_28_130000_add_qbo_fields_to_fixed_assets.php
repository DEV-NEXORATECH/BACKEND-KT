<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->text('description')->nullable()->after('asset_name');
            $table->boolean('is_non_depreciable')->default(false)->after('asset_category_id');
            $table->foreignId('asset_gl_account_id')->nullable()->after('net_book_value')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('depreciation_gl_account_id')->nullable()->after('asset_gl_account_id')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('accumulated_gl_account_id')->nullable()->after('depreciation_gl_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['asset_gl_account_id']);
            $table->dropForeign(['depreciation_gl_account_id']);
            $table->dropForeign(['accumulated_gl_account_id']);
            $table->dropColumn(['description', 'is_non_depreciable', 'asset_gl_account_id', 'depreciation_gl_account_id', 'accumulated_gl_account_id']);
        });
    }
};
