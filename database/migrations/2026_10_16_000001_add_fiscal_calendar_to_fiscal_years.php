<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            if (! Schema::hasColumn('fiscal_years', 'calendar_code')) {
                $table->string('calendar_code', 30)->default('JAN_DEC')->after('name');
            }
            if (! Schema::hasColumn('fiscal_years', 'start_month')) {
                $table->unsignedTinyInteger('start_month')->default(1)->after('calendar_code');
            }
        });

        // The same reporting year may exist in more than one calendar.
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropUnique('fiscal_years_year_unique');
            $table->unique(['year', 'calendar_code'], 'fiscal_years_year_calendar_unique');
            $table->index('calendar_code');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropUnique('fiscal_years_year_calendar_unique');
            $table->dropIndex(['calendar_code']);
            $table->unique('year', 'fiscal_years_year_unique');
            $table->dropColumn(['calendar_code', 'start_month']);
        });
    }
};
