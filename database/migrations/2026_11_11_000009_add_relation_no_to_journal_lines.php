<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->after('budget_line_id')->index();
        });

        DB::table('journal_lines')
            ->whereNull('relation_no')
            ->whereNotNull('budget_line_id')
            ->orderBy('id')
            ->chunkById(250, function ($lines): void {
                $relations = DB::table('budget_lines')
                    ->whereIn('id', $lines->pluck('budget_line_id')->filter()->unique())
                    ->whereNotNull('relation_no')
                    ->pluck('relation_no', 'id');

                foreach ($lines as $line) {
                    $relationNo = $relations->get($line->budget_line_id);
                    if ($relationNo) {
                        DB::table('journal_lines')->where('id', $line->id)->update([
                            'relation_no' => $relationNo,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropIndex(['relation_no']);
            $table->dropColumn('relation_no');
        });
    }
};
