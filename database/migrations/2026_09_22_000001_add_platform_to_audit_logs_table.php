<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'platform')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('platform', 12)->default('web')->index()->after('module');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('audit_logs', 'platform')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('audit_logs_platform_index');
                $table->dropColumn('platform');
            });
        }
    }
};
