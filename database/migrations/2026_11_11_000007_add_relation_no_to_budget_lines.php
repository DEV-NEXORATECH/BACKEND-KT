<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->index()->after('project_id');
        });

        DB::table('budget_lines')
            ->whereNull('relation_no')
            ->orderBy('id')
            ->eachById(function ($budgetLine): void {
                $relationNo = null;

                if ($budgetLine->project_id) {
                    $relationNo = DB::table('projects')
                        ->where('id', $budgetLine->project_id)
                        ->value('relation_no');
                }

                if (! $relationNo && $budgetLine->grant_agreement_id) {
                    $relationNo = DB::table('grant_agreements')
                        ->where('id', $budgetLine->grant_agreement_id)
                        ->value('relation_no');
                }

                DB::table('budget_lines')
                    ->where('id', $budgetLine->id)
                    ->update(['relation_no' => $relationNo]);
            });
    }

    public function down(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->dropIndex(['relation_no']);
            $table->dropColumn('relation_no');
        });
    }
};
