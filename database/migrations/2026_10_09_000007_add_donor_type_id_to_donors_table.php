<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('donors', 'donor_type_id')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->foreignId('donor_type_id')->nullable()->after('type')->constrained('donor_types')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('donors', 'donor_type_id')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->dropForeign(['donor_type_id']);
                $table->dropColumn('donor_type_id');
            });
        }
    }
};
