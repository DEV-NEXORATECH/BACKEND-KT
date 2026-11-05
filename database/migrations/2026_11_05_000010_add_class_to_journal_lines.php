<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('journal_lines', 'class')) {
            Schema::table('journal_lines', fn (Blueprint $table) => $table->string('class', 120)->nullable()->after('line_description'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_lines', 'class')) Schema::table('journal_lines', fn (Blueprint $table) => $table->dropColumn('class'));
    }
};
