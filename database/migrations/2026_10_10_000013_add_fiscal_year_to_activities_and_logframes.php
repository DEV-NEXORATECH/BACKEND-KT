<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['activities', 'project_logframes'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'fiscal_year_id')) continue;
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('fiscal_year_id')->nullable()->after('id')->constrained('fiscal_years')->nullOnDelete();
                $blueprint->index('fiscal_year_id');
            });
        }

        $activeFiscalYearId = DB::table('fiscal_years')->where('is_active', true)->orderByDesc('year')->value('id');
        if ($activeFiscalYearId) {
            foreach ($this->tables as $table) {
                DB::table($table)->whereNull('fiscal_year_id')->update(['fiscal_year_id' => $activeFiscalYearId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'fiscal_year_id')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropConstrainedForeignId('fiscal_year_id'));
            }
        }
    }
};
