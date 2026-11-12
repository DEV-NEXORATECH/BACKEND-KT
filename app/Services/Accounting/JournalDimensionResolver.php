<?php

namespace App\Services\Accounting;

use App\Models\Master\BudgetLine;
use App\Models\Master\Project;
use Illuminate\Validation\ValidationException;

class JournalDimensionResolver
{
    public function resolve(array $line, int $index = 0, bool $posting = false): array
    {
        $fail = static function (string $field, string $message) use ($index): never {
            throw ValidationException::withMessages(["lines.{$index}.{$field}" => $message]);
        };
        $budget = empty($line['budget_line_id']) ? null : BudgetLine::withoutGlobalScope('fiscal_year_context')->find($line['budget_line_id']);
        if (!empty($line['budget_line_id']) && !$budget) {
            $fail('budget_line_id', 'Budget Line tidak tersedia atau sudah dihapus.');
        }
        $projectId = $line['project_id'] ?? $budget?->project_id;
        $project = $projectId ? Project::withoutGlobalScope('fiscal_year_context')->find($projectId) : null;
        if ($projectId && !$project) {
            $fail('project_id', 'Project tidak tersedia atau sudah dihapus.');
        }
        if ($budget && (!$project || (int)$budget->project_id !== (int)$project->id)) {
            $fail('budget_line_id', 'Budget Line bukan milik Project.');
        }
        if ($project) {
            $grant = \App\Models\Master\GrantAgreement::withoutGlobalScope('fiscal_year_context')->find($project->grant_agreement_id);
            if (!$grant || !$grant->relation_no) {
                $fail('project_id', 'Project harus memiliki Grant Agreement dan nomor relasi terintegrasi.');
            }
            foreach ([$project->relation_no, $budget?->relation_no, $line['relation_no'] ?? null] as $relation) {
                if ($relation && $relation !== $grant->relation_no) {
                    $fail('relation_no', 'Nomor relasi Project, Grant dan Budget Line berbeda.');
                }
            }
            if ($budget && (int)$budget->grant_agreement_id !== (int)$grant->id) {
                $fail('budget_line_id', 'Grant Budget Line berbeda dari Grant Project.');
            }
            foreach (['grant_agreement_id' => $grant->id, 'donor_id' => $grant->donor_id, 'program_id' => $project->program_id] as $field => $expected) {
                if (!empty($line[$field]) && (int)$line[$field] !== (int)$expected) {
                    $fail($field, 'Dimensi bertentangan dengan sumber relasi.');
                }
                $line[$field] = $expected;
            }
            $line['project_id'] = $project->id;
            $line['relation_no'] = $grant->relation_no;
        }
        if (!$project && !empty($line['grant_agreement_id'])) {
            $grant = \App\Models\Master\GrantAgreement::withoutGlobalScope('fiscal_year_context')->find($line['grant_agreement_id']);
            if (!$grant) $fail('grant_agreement_id', 'Grant tidak tersedia.');
            if (!empty($line['donor_id']) && (int)$line['donor_id'] !== (int)$grant->donor_id) $fail('donor_id', 'Donor berbeda dari Grant.');
            if (!empty($line['relation_no']) && $line['relation_no'] !== $grant->relation_no) $fail('relation_no', 'Nomor relasi berbeda dari Grant.');
            $line['donor_id'] = $grant->donor_id;
            $line['relation_no'] = $grant->relation_no;
        }
        $account = \App\Models\Master\ChartOfAccount::withoutGlobalScope('fiscal_year_context')->find($line['account_id']);
        if (!$account) $fail('account_id', 'Akun COA tidak tersedia atau sudah dihapus.');
        if ($posting && $account?->account_type === 'expense' && (!$budget || !$project || empty($line['donor_id']) || empty($line['relation_no']))) {
            $fail('budget_line_id', 'Lengkapi dimensi budget pengeluaran sebelum posting.');
        }
        return $line;
    }
}
