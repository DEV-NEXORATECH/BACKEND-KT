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
        [$project, $grant, $expectedRelationNo] = $this->budgetRelationContext();

        $updates = [];
        if (! $this->filled('project_id') && $project) {
            $updates['project_id'] = $project->id;
        }
        if (! $this->filled('grant_agreement_id') && $grant) {
            $updates['grant_agreement_id'] = $grant->id;
        }
        if (! $this->filled('relation_no') && $expectedRelationNo) {
            $updates['relation_no'] = $expectedRelationNo;
        }
        if ($updates !== []) {
            $this->merge($updates);
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
        $relationNo = trim((string) $this->input('relation_no', ''));

        if (! $projectId && ! $grantId && $this->route('id')) {
            $budgetLine = BudgetLine::query()
                ->withoutGlobalScope('fiscal_year_context')
                ->find($this->route('id'));
            $projectId = $budgetLine?->project_id;
            $grantId = $budgetLine?->grant_agreement_id;
        }

        $project = $projectId
            ? Project::query()->with('grantAgreement:id,relation_no')->find($projectId)
            : ($relationNo !== '' ? Project::query()->with('grantAgreement:id,relation_no')->where('relation_no', $relationNo)->first() : null);

        $grant = $grantId
            ? GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->find($grantId)
            : ($project?->grantAgreement
                ?: ($relationNo !== '' ? GrantAgreement::query()->withoutGlobalScope('fiscal_year_context')->where('relation_no', $relationNo)->first() : null));

        $expectedRelationNo = $project?->relation_no ?: $project?->grantAgreement?->relation_no ?: $grant?->relation_no;

        return [$project, $grant, $expectedRelationNo];
    }
}
