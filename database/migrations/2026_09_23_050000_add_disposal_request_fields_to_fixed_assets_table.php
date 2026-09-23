<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('fixed_assets', 'disposal_requested_date')) $table->date('disposal_requested_date')->nullable()->after('disposed_date');
            if (! Schema::hasColumn('fixed_assets', 'disposal_requested_reason')) $table->text('disposal_requested_reason')->nullable()->after('disposal_requested_date');
        });
    }
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (Schema::hasColumn('fixed_assets', 'disposal_requested_reason')) $table->dropColumn('disposal_requested_reason');
            if (Schema::hasColumn('fixed_assets', 'disposal_requested_date')) $table->dropColumn('disposal_requested_date');
        });
    }
};
