<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('journal_lines', 'name')) $table->string('name', 150)->nullable()->after('class');
            if (! Schema::hasColumn('journal_lines', 'location')) $table->string('location', 150)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            if (Schema::hasColumn('journal_lines', 'location')) $table->dropColumn('location');
            if (Schema::hasColumn('journal_lines', 'name')) $table->dropColumn('name');
        });
    }
};
