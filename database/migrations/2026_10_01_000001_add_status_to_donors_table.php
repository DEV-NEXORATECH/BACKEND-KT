<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('donors', 'status')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->enum('status', ['active', 'terminated', 'completed', 'inactive'])
                    ->default('active')
                    ->after('is_active')
                    ->index();
            });
        }

        DB::table('donors')->where('is_active', true)->update(['status' => 'active']);
        DB::table('donors')->where('is_active', false)->update(['status' => 'inactive']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('donors', 'status')) {
            Schema::table('donors', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
