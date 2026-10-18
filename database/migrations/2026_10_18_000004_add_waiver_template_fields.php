<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_waivers', function (Blueprint $table) {
            $table->string('donor_name', 200)->nullable()->after('date');
            $table->json('vendor_rows')->nullable()->after('donor_name');
            $table->string('prepared_by', 120)->nullable()->after('vendor_rows');
            $table->string('prepared_title', 120)->nullable()->after('prepared_by');
            $table->string('authorised_by', 120)->nullable()->after('prepared_title');
            $table->string('authorised_title', 120)->nullable()->after('authorised_by');
            $table->string('verified_by', 120)->nullable()->after('authorised_title');
            $table->string('verified_title', 120)->nullable()->after('verified_by');
            $table->text('remarks')->nullable()->after('verified_title');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_waivers', function (Blueprint $table) {
            $table->dropColumn(['donor_name', 'vendor_rows', 'prepared_by', 'prepared_title', 'authorised_by', 'authorised_title', 'verified_by', 'verified_title', 'remarks']);
        });
    }
};
