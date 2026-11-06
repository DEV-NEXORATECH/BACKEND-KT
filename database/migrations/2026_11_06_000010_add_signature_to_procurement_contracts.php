<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('procurement_contracts', 'signature_path')) {
            Schema::table('procurement_contracts', function (Blueprint $table) {
                $table->string('signature_path')->nullable()->after('attachments');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurement_contracts', 'signature_path')) {
            Schema::table('procurement_contracts', fn (Blueprint $table) => $table->dropColumn('signature_path'));
        }
    }
};
