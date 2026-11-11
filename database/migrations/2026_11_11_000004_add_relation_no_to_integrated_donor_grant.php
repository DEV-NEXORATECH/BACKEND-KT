<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->unique()->after('grant_no');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->index()->after('code');
        });

        DB::table('grant_agreements')
            ->whereNull('relation_no')
            ->orderBy('id')
            ->eachById(function ($grant): void {
                $project = DB::table('projects')
                    ->where('grant_agreement_id', $grant->id)
                    ->orderBy('id')
                    ->first();

                $relationNo = sprintf(
                    'KT-REL-D%d-G%d-P%d',
                    $grant->donor_id,
                    $grant->id,
                    $project?->id ?? 0
                );

                DB::table('grant_agreements')->where('id', $grant->id)->update(['relation_no' => $relationNo]);
                DB::table('projects')->where('grant_agreement_id', $grant->id)->update(['relation_no' => $relationNo]);
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['relation_no']);
            $table->dropColumn('relation_no');
        });

        Schema::table('grant_agreements', function (Blueprint $table) {
            $table->dropUnique(['relation_no']);
            $table->dropColumn('relation_no');
        });
    }
};
