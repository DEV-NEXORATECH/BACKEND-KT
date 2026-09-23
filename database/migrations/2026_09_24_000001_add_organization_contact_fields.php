<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->after('npwp');
            $table->string('website', 255)->nullable()->after('email');
            $table->string('pass_code', 100)->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['email', 'website', 'pass_code']);
        });
    }
};
