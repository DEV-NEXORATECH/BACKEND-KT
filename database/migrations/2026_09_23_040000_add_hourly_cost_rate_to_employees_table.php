<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'hourly_cost_rate')) {
            Schema::table('employees', fn (Blueprint $table) => $table->decimal('hourly_cost_rate', 18, 2)->nullable()->after('position'));
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'hourly_cost_rate')) Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('hourly_cost_rate'));
    }
};
