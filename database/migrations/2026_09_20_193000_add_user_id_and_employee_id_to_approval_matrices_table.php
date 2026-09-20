<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('approval_matrices', 'user_id')) {
            Schema::table('approval_matrices', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('role_id')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('approval_matrices', 'employee_id')) {
            Schema::table('approval_matrices', function (Blueprint $table) {
                $table->foreignId('employee_id')->nullable()->after('user_id')->constrained('employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('approval_matrices', 'employee_id')) {
            Schema::table('approval_matrices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_id');
            });
        }

        if (Schema::hasColumn('approval_matrices', 'user_id')) {
            Schema::table('approval_matrices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
