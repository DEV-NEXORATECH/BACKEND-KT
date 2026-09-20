<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_matrices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('role_id')->constrained('users')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->after('user_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_matrices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
