<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('expense_requests', 'funding_mode')) return;

        Schema::table('expense_requests', function (Blueprint $table) {
            $table->enum('funding_mode', ['solo', 'joint'])->default('solo')->after('expense_type');
            $table->foreignId('second_donor_id')->nullable()->after('donor_id')->constrained('donors')->nullOnDelete();
            $table->foreignId('second_program_id')->nullable()->after('program_id')->constrained('programs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('expense_requests')) return;
        Schema::table('expense_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('second_donor_id');
            $table->dropConstrainedForeignId('second_program_id');
            $table->dropColumn('funding_mode');
        });
    }
};
