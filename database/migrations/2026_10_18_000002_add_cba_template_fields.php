<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('selection_reason');
            $table->text('bidding_results')->nullable()->after('remarks');
            $table->string('prepared_by', 120)->nullable()->after('bidding_results');
            $table->string('budget_holder_name', 120)->nullable()->after('prepared_by');
            $table->string('panelist_1_name', 120)->nullable()->after('budget_holder_name');
            $table->string('panelist_2_name', 120)->nullable()->after('panelist_1_name');
            $table->string('panelist_3_name', 120)->nullable()->after('panelist_2_name');
            $table->json('bid_comparison')->nullable()->after('panelist_3_name');
        });
    }

    public function down(): void
    {
        Schema::table('comparative_bid_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'remarks', 'bidding_results', 'prepared_by', 'budget_holder_name',
                'panelist_1_name', 'panelist_2_name', 'panelist_3_name', 'bid_comparison',
            ]);
        });
    }
};
