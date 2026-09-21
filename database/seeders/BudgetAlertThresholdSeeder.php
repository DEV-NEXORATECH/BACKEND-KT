<?php

namespace Database\Seeders;

use App\Models\Budget\BudgetCommitment;
use App\Models\Master\BudgetCategory;
use App\Models\Master\BudgetLine;
use App\Models\Master\Project;
use Illuminate\Database\Seeder;
use RuntimeException;

class BudgetAlertThresholdSeeder extends Seeder
{
    /**
     * Seed budget lines that exercise every AI budget alert threshold.
     */
    public function run(): void
    {
        $project = Project::query()->where('code', 'PRJ-2026-FORD-01')->first();
        $category = BudgetCategory::query()->where('code', 'BCAT-200')->first();

        if (! $project || ! $category) {
            throw new RuntimeException(
                'Run MasterDataSeeder before BudgetAlertThresholdSeeder so its sample project and budget category exist.'
            );
        }

        $thresholds = [
            ['percent' => 25, 'label' => 'Aman'],
            ['percent' => 50, 'label' => 'Normal'],
            ['percent' => 75, 'label' => 'Waspada'],
            ['percent' => 100, 'label' => 'Habis'],
            ['percent' => 110, 'label' => 'Overbudget'],
        ];

        foreach ($thresholds as $threshold) {
            $percent = $threshold['percent'];
            $reference = "SEED-AI-BUDGET-{$percent}";
            $line = BudgetLine::query()->updateOrCreate(
                ['line_code' => "AI-ALERT-{$percent}"],
                [
                    'grant_agreement_id' => $project->grant_agreement_id,
                    'project_id' => $project->id,
                    'budget_category_id' => $category->id,
                    'description' => "AI alert test — {$threshold['label']} ({$percent}%)",
                    'unit_price' => 1_000_000,
                    'quantity' => 1,
                    'total_amount' => 1_000_000,
                    'is_active' => true,
                ],
            );

            BudgetCommitment::query()->updateOrCreate(
                ['budget_line_id' => $line->id, 'reference' => $reference],
                [
                    'source_type' => 'seeded_ai_budget_alert',
                    'source_id' => null,
                    'amount' => $percent * 10_000,
                    'status' => 'open',
                ],
            );
        }
    }
}
