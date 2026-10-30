<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_requests', 'verification_checklist')) {
                $table->json('verification_checklist')->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('expense_requests', 'cdv_number')) {
                $table->string('cdv_number', 80)->nullable()->after('verification_checklist')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('expense_requests', function (Blueprint $table) {
            if (Schema::hasColumn('expense_requests', 'cdv_number')) $table->dropIndex(['cdv_number']);
            foreach (['verification_checklist', 'cdv_number'] as $column) {
                if (Schema::hasColumn('expense_requests', $column)) $table->dropColumn($column);
            }
        });
    }
};
