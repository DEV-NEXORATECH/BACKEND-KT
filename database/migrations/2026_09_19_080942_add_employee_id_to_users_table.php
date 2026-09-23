<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('role_id')->constrained('employees')->nullOnDelete();
            }
        });

        DB::table('users as u')
            ->whereNull('u.employee_id')
            ->update([
                'u.employee_id' => DB::raw('(select e.id from employees e where e.email = u.email limit 1)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
        });
    }
};