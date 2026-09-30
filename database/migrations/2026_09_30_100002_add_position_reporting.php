<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (! Schema::hasColumn('positions', 'reports_to_position_id')) {
                $table->foreignId('reports_to_position_id')->nullable()->after('department_id')->constrained('positions')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'reports_to_position_id')) $table->dropConstrainedForeignId('reports_to_position_id');
        });
    }
};
