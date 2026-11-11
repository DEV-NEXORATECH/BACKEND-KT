<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_logframes', function (Blueprint $table) {
            $table->string('relation_no', 100)->nullable()->index()->after('project_id');
        });

        DB::table('project_logframes')
            ->whereNull('relation_no')
            ->orderBy('id')
            ->eachById(function ($framework): void {
                $relationNo = DB::table('projects')->where('id', $framework->project_id)->value('relation_no');
                DB::table('project_logframes')->where('id', $framework->id)->update(['relation_no' => $relationNo]);
            });
    }

    public function down(): void
    {
        Schema::table('project_logframes', function (Blueprint $table) {
            $table->dropIndex(['relation_no']);
            $table->dropColumn('relation_no');
        });
    }
};
