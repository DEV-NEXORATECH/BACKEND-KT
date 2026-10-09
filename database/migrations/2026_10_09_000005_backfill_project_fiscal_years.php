<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Older project creation requests stored the scalar fiscal_year_id in
        // the request but never populated the project_fiscal_year pivot.
        // Recover those projects from their linked grant agreement year.
        $legacyProjects = DB::table('projects as p')
            ->join('grant_agreements as g', function (JoinClause $join) {
                $join->on('g.id', '=', 'p.grant_agreement_id')
                    ->whereNotNull('g.fiscal_year_id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('project_fiscal_year as pfy')
                    ->whereColumn('pfy.project_id', 'p.id');
            })
            ->select(['p.id as project_id', 'g.fiscal_year_id'])
            ->get();

        foreach ($legacyProjects as $project) {
            DB::table('project_fiscal_year')->insertOrIgnore([
                'project_id' => $project->project_id,
                'fiscal_year_id' => $project->fiscal_year_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The backfilled pivot rows are valid project-year relationships and
        // must not be removed during rollback.
    }
};
