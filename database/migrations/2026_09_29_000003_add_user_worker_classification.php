<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('worker_type', 20)->default('internal')->after('role_id');
            $table->string('external_party_name', 180)->nullable()->after('worker_type');
            $table->string('contract_reference', 120)->nullable()->after('external_party_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['worker_type', 'external_party_name', 'contract_reference']);
        });
    }
};
