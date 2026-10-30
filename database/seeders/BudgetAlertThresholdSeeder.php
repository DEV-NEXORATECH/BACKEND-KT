<?php

namespace Database\Seeders;

use App\Models\Budget\BudgetCommitment;
use Illuminate\Database\Seeder;

class BudgetAlertThresholdSeeder extends Seeder
{
    /**
     * Remove old synthetic AI alert fixtures.
     *
     * AI alerts must be calculated from real budget lines. Synthetic
     * AI-ALERT-* records were incorrectly created as selectable master data.
     */
    public function run(): void
    {
        $lineIds = \DB::table('budget_lines')
            ->where('line_code', 'like', 'AI-ALERT-%')
            ->pluck('id');

        if ($lineIds->isNotEmpty()) {
            BudgetCommitment::query()
                ->whereIn('budget_line_id', $lineIds)
                ->where('source_type', 'seeded_ai_budget_alert')
                ->delete();
            \DB::table('budget_lines')->whereIn('id', $lineIds)->delete();
        }
    }
}
