<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'join_date')) $table->date('join_date')->nullable()->after('email');
            if (! Schema::hasColumn('employees', 'end_date')) $table->date('end_date')->nullable()->after('join_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'end_date')) $table->dropColumn('end_date');
            if (Schema::hasColumn('employees', 'join_date')) $table->dropColumn('join_date');
        });
    }
};
