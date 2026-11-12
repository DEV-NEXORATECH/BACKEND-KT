<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('journal_lines')
            ->orderBy('id')
            ->chunkById(250, function ($lines): void {
                foreach ($lines as $line) {
                    $budgetLine = $line->budget_line_id
                        ? DB::table('budget_lines')->where('id', $line->budget_line_id)->first()
                        : null;
                    $projectId = $line->project_id ?: $budgetLine?->project_id;
                    $project = $projectId
                        ? DB::table('projects')->where('id', $projectId)->first()
                        : null;
                    $grantId = $project?->grant_agreement_id
                        ?: $line->grant_agreement_id
                        ?: $budgetLine?->grant_agreement_id;
                    $grant = $grantId
                        ? DB::table('grant_agreements')->where('id', $grantId)->first()
                        : null;
                    $relationNo = $line->relation_no
                        ?: $project?->relation_no
                        ?: $grant?->relation_no
                        ?: $budgetLine?->relation_no;

                    DB::table('journal_lines')->where('id', $line->id)->update([
                        'project_id' => $projectId,
                        'grant_agreement_id' => $grant?->id,
                        'donor_id' => $grant?->donor_id ?: $line->donor_id,
                        'program_id' => $project?->program_id ?: $line->program_id,
                        'relation_no' => $relationNo,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible. Removing populated
        // relation values would detach historical journals from donor reports.
    }
};
