<?php

namespace App\Services\Budget;

use App\Models\Accounting\JournalLine;
use App\Models\Budget\BudgetCommitment;
use App\Models\Master\BudgetLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BudgetMonitoringService
{
    public function summary(array $filters = []): Collection
    {
        $budgetLines = BudgetLine::query()
            ->with([
                'grantAgreement:id,grant_no,agreement_name,donor_id',
                'grantAgreement.donor:id,code,name',
                'project:id,code,name,program_id',
                'project.program:id,code,name',
                'budgetCategory:id,code,name',
                'glAccount:id,code,name',
            ])
            ->when($filters['budget_line_id'] ?? null, fn (Builder $query, $budgetLineId) => $query->whereKey($budgetLineId))
            ->when($filters['project_id'] ?? null, fn (Builder $query, $projectId) => $query->where('project_id', $projectId))
            ->when($filters['grant_agreement_id'] ?? null, fn (Builder $query, $grantId) => $query->where('grant_agreement_id', $grantId))
            ->when($filters['budget_category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('budget_category_id', $categoryId))
            ->when($filters['budget_line_ids'] ?? null, fn (Builder $query, array $ids) => $query->whereIn('id', $ids))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('line_code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('line_code')
            ->get();

        $ids = $budgetLines->pluck('id');

        $actuals = JournalLine::query()
            ->selectRaw('budget_line_id, COALESCE(SUM(debit - credit), 0) as actual_amount')
            ->whereIn('budget_line_id', $ids)
            ->whereHas('journal', fn (Builder $query) => $query->where('status', 'posted'))
            ->groupBy('budget_line_id')
            ->pluck('actual_amount', 'budget_line_id');

        $commitments = BudgetCommitment::query()
            ->selectRaw('budget_line_id, COALESCE(SUM(amount), 0) as committed_amount')
            ->whereIn('budget_line_id', $ids)
            ->where('status', 'open')
            ->groupBy('budget_line_id')
            ->pluck('committed_amount', 'budget_line_id');

        return $budgetLines->map(function (BudgetLine $line) use ($actuals, $commitments) {
            $approved = round((float) ($line->base_amount ?? $line->total_amount), 2);
            $actual = round((float) ($actuals[$line->id] ?? 0), 2);
            $committed = round((float) ($commitments[$line->id] ?? 0), 2);
            $available = round($approved - $actual - $committed, 2);
            $utilization = $approved > 0 ? round((($actual + $committed) / $approved) * 100, 2) : 0;

            return [
                'budget_line_id' => $line->id,
                'line_code' => $line->line_code,
                'description' => $line->description,
                'approved_budget' => $approved,
                'actual' => $actual,
                'committed' => $committed,
                'available' => $available,
                'utilization_percent' => $utilization,
                'status' => $available < 0 ? 'over_budget' : ($utilization >= 90 ? 'warning' : 'healthy'),
                'donor' => $line->grantAgreement?->donor ? [
                    'id' => $line->grantAgreement->donor->id,
                    'code' => $line->grantAgreement->donor->code,
                    'name' => $line->grantAgreement->donor->name,
                ] : null,
                'grant_agreement' => $line->grantAgreement ? [
                    'id' => $line->grantAgreement->id,
                    'code' => $line->grantAgreement->grant_no,
                    'name' => $line->grantAgreement->agreement_name,
                ] : null,
                'program' => $line->project?->program ? [
                    'id' => $line->project->program->id,
                    'code' => $line->project->program->code,
                    'name' => $line->project->program->name,
                ] : null,
                'project' => $line->project ? [
                    'id' => $line->project->id,
                    'code' => $line->project->code,
                    'name' => $line->project->name,
                    'budget_holder' => $line->project->manager_name,
                ] : null,
                'budget_category' => $line->budgetCategory ? [
                    'id' => $line->budgetCategory->id,
                    'code' => $line->budgetCategory->code,
                    'name' => $line->budgetCategory->name,
                ] : null,
                'gl_account' => $line->glAccount ? [
                    'id' => $line->glAccount->id,
                    'code' => $line->glAccount->code,
                    'name' => $line->glAccount->name,
                ] : null,
            ];
        });
    }

    public function validate(int $budgetLineId, float $amount): array
    {
        $item = $this->summary(['budget_line_id' => $budgetLineId])
            ->firstWhere('budget_line_id', $budgetLineId);

        if (! $item) {
            $line = BudgetLine::query()->findOrFail($budgetLineId);
            $item = $this->summary(['project_id' => $line->project_id])
                ->firstWhere('budget_line_id', $budgetLineId);
        }

        $availableAfter = round((float) $item['available'] - $amount, 2);

        return [
            ...$item,
            'requested_amount' => round($amount, 2),
            'available_after' => $availableAfter,
            'allowed' => $availableAfter >= 0,
            'severity' => $availableAfter >= 0 ? 'ok' : 'blocked',
            'message' => $availableAfter >= 0
                ? 'Budget tersedia untuk transaksi ini.'
                : 'Nilai transaksi melebihi available budget.',
        ];
    }

    /**
     * Create an open budget commitment for a given source document.
     */
    public function commit(int $budgetLineId, string $sourceType, ?int $sourceId, float $amount, string $reference, int $userId): BudgetCommitment
    {
        return BudgetCommitment::query()->updateOrCreate(
            [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'budget_line_id' => $budgetLineId,
            ],
            [
                'reference' => $reference,
                'amount' => round($amount, 2),
                'status' => 'open',
                'created_by' => $userId,
            ],
        );
    }

    /**
     * Flag an existing open commitment as released so the committed amount
     * returns to the available budget.
     */
    public function release(BudgetCommitment $commitment, string $status = 'released', ?int $userId = null): BudgetCommitment
    {
        if ($commitment->status === 'open') {
            $commitment->update([
                'status' => $status,
                'released_by' => $userId,
                'released_at' => now(),
            ]);
        }

        return $commitment;
    }

    /**
     * Release all open commitments scoped to a source document.
     */
    public function releaseForSource(string $sourceType, int $sourceId, string $status = 'released', ?int $userId = null): int
    {
        return BudgetCommitment::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', 'open')
            ->update([
                'status' => $status,
                'released_by' => $userId,
                'released_at' => now(),
            ]);
    }

    /**
     * Mark an open commitment as converted, meaning budget was actually
     * consumed (actual expense has been posted against the budget line).
     */
    public function convert(BudgetCommitment $commitment, int $userId): BudgetCommitment
    {
        $this->release($commitment, 'converted', $userId);

        return $commitment;
    }
}
