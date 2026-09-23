<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tax_transactions')) {
            Schema::table('tax_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('tax_transactions', 'reported_by')) $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
                if (! Schema::hasColumn('tax_transactions', 'reported_at')) $table->timestamp('reported_at')->nullable();
                if (! Schema::hasColumn('tax_transactions', 'created_by')) $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tax_transactions')) {
            Schema::table('tax_transactions', function (Blueprint $table) {
                if (Schema::hasColumn('tax_transactions', 'reported_by')) $table->dropConstrainedForeignId('reported_by');
                if (Schema::hasColumn('tax_transactions', 'reported_at')) $table->dropColumn('reported_at');
            });
        }
    }
};
