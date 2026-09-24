<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->string('agreement_no', 80)->nullable()->unique()->after('grant_no');
        });
    }

    public function down(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->dropUnique(['agreement_no']);
            $table->dropColumn('agreement_no');
        });
    }
};
