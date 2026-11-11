<?php

namespace App\Http\Requests\Master\Concerns;

use App\Models\Master\BudgetLine;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use Illuminate\Validation\Validator;

trait ValidatesBudgetLineRelation
{
    protected function mergeBudgetRelationContext(): void
    {
        [, , $expectedRelationNo] = $this->budgetRelationContext();

        if (! $this->filled('relation_no') && $expectedRelationNo) {
            $this->merge(['relation_no' => $expectedRelationNo]);
        }
    }

    protected function validateBudgetRelationContext(Validator $validator): void
    {
        [$project, $grant, $expectedRelationNo] = $this->budgetRelationContext();

        if ($project && $grant && $project->grant_agreement_id && (int) $project->grant_agreement_id !== (int) $grant->id) {
            $validator->errors()->add('grant_agreement_id', 'Grant Agreement tidak sesuai dengan project yang dipilih.');
        }

        $relationNo = trim((string) $this->input('relation_no', ''));
        if ($relationNo !== '' && $expectedRelationNo && strcasecmp($expectedRelationNo, $relationNo) !== 0) {
            $validator->errors()->add('relation_no', 'Nomor Relasi tidak sesuai dengan project atau Grant Agreement yang dipilih.');
        }
    }

    private function budgetRelationContext(): array
    {
        $projectId = $this->input('project_id');
        $grantId = $this->input('grant_agreement_id');

        if (! $projectId && ! $grantId && $this->route('id')) {
            $budgetLine = BudgetLine::query()
                ->withoutGlobalScope('fiscal_year_context')
                ->find($this->route('id'));
            $projectId = $budgetLine?->project_id;
            $grantId = $budgetLine?->grant_agreement_id;
        }

        $project = $projectId ? Project::query()->with('grantAgreement:id,relation_no')->find($projectId) : null;
        $grant = $grantId ? GrantAgreement::query()->find($grantId) : $project?->grantAgreement;
        $expectedRelationNo = $project?->relation_no ?: $project?->grantAgreement?->relation_no ?: $grant?->relation_no;

        return [$project, $grant, $expectedRelationNo];
    }
}
