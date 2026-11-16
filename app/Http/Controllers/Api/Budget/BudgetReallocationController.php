<?php

namespace App\Http\Controllers\Api\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget\BudgetReallocation;
use App\Models\Master\BudgetLine;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\Budget\BudgetMonitoringService;
use App\Services\Rbac\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BudgetReallocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'fiscal_year_id' => ['nullable', 'integer', 'exists:fiscal_years,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'relation_no' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,submitted,approved,rejected,applied'],
        ]);

        $query = BudgetReallocation::query()
            ->with($this->relations())
            ->when($filters['project_id'] ?? null, fn (Builder $query, $id) => $query->where('project_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['fiscal_year_id'] ?? null, fn (Builder $query, $id) => $query->whereHas('fromLine', fn (Builder $line) => $line->withoutGlobalScope('fiscal_year_context')->where('fiscal_year_id', $id)))
            ->when($filters['relation_no'] ?? null, fn (Builder $query, $relationNo) => $query->whereHas('fromLine', function (Builder $line) use ($relationNo) {
                $line->withoutGlobalScope('fiscal_year_context')->whereRaw("COALESCE(NULLIF(budget_lines.relation_no, ''),
                    (SELECT NULLIF(relation_no, '') FROM projects WHERE projects.id = budget_lines.project_id AND deleted_at IS NULL),
                    (SELECT relation_no FROM grant_agreements WHERE grant_agreements.id = budget_lines.grant_agreement_id AND deleted_at IS NULL)) = ?", [$relationNo]);
            }));
        app(DataScopeService::class)->applyScope($query, $request->user(), 'created_by', 'project_id', null, []);
        $rows = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request, BudgetMonitoringService $budget): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'from_budget_line_id' => ['required', 'integer', 'different:to_budget_line_id', 'exists:budget_lines,id'],
            'to_budget_line_id' => ['required', 'integer', 'exists:budget_lines,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $from = $this->line((int) $data['from_budget_line_id']);
        $to = $this->line((int) $data['to_budget_line_id']);
        $this->assertCompatible($from, $to, isset($data['project_id']) ? (int) $data['project_id'] : null);
        $available = $budget->validate($from->id, (float) $data['amount']);
        if (! $available['allowed']) {
            throw ValidationException::withMessages(['amount' => $available['message']]);
        }

        $record = BudgetReallocation::create([
            'project_id' => $from->project_id,
            'from_budget_line_id' => $from->id,
            'to_budget_line_id' => $to->id,
            'amount' => round((float) $data['amount'], 2),
            'reason' => trim($data['reason']),
            'reference' => 'BR-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['success' => true, 'data' => $record->load($this->relations())], 201);
    }

    public function submit(Request $request, BudgetReallocation $budgetReallocation, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($budgetReallocation->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang dapat diajukan.']);
        }

        $this->assertCompatible($this->line($budgetReallocation->from_budget_line_id), $this->line($budgetReallocation->to_budget_line_id), $budgetReallocation->project_id);
        $budgetReallocation->update(['status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
        $workflow->start('budget_reallocation', $budgetReallocation, (float) $budgetReallocation->amount, $request->user()->id);

        return response()->json(['success' => true, 'data' => $budgetReallocation->fresh()->load($this->relations())]);
    }

    public function approve(Request $request, BudgetReallocation $budgetReallocation, BudgetMonitoringService $budget, ApprovalWorkflowService $workflow): JsonResponse
    {
        $result = DB::transaction(function () use ($request, $budgetReallocation, $budget, $workflow) {
            $record = BudgetReallocation::query()->whereKey($budgetReallocation->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Hanya pengajuan yang sudah disubmit yang dapat diproses.']);
            }

            $from = BudgetLine::withoutGlobalScope('fiscal_year_context')->whereKey($record->from_budget_line_id)->lockForUpdate()->firstOrFail();
            $to = BudgetLine::withoutGlobalScope('fiscal_year_context')->whereKey($record->to_budget_line_id)->lockForUpdate()->firstOrFail();
            $from->load(['project', 'grantAgreement']);
            $to->load(['project', 'grantAgreement']);
            $this->assertCompatible($from, $to, $record->project_id);

            $available = $budget->validate($from->id, (float) $record->amount);
            if (! $available['allowed']) {
                throw ValidationException::withMessages(['amount' => $available['message']]);
            }

            $approval = $workflow->approve('budget_reallocation', $record, $request->user(), $request->input('notes'));
            if ($approval['managed'] && ! $approval['completed']) {
                return ['pending' => true, 'next_level' => $approval['next_level'], 'record' => $record];
            }
            if (! $approval['managed'] && $workflow->requiresApproval('budget_reallocation', $record, (float) $record->amount)) {
                throw ValidationException::withMessages(['approval' => 'Realokasi harus melalui Approval Matrix.']);
            }

            $from->total_amount = round((float) $from->total_amount - (float) $record->amount, 2);
            $from->save();
            $to->total_amount = round((float) $to->total_amount + (float) $record->amount, 2);
            $to->save();
            $record->update([
                'status' => 'applied',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'decision_notes' => $request->input('notes'),
            ]);

            return ['pending' => false, 'record' => $record];
        });

        $message = $result['pending']
            ? "Realokasi menunggu approver level {$result['next_level']}."
            : 'Realokasi berhasil diterapkan ke budget sumber dan tujuan.';

        return response()->json(['success' => true, 'message' => $message, 'data' => $result['record']->fresh()->load($this->relations())]);
    }

    private function relations(): array
    {
        return [
            'project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'project.bankAccount.glAccount',
            'fromLine' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'fromLine.project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'fromLine.project.bankAccount.glAccount',
            'fromLine.grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'fromLine.grantAgreement.donor',
            'fromLine.grantAgreement.bankAccount.glAccount',
            'fromLine.fiscalYear', 'fromLine.currency', 'fromLine.glAccount',
            'toLine' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'toLine.project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'toLine.project.bankAccount.glAccount',
            'toLine.grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'toLine.grantAgreement.donor',
            'toLine.grantAgreement.bankAccount.glAccount',
            'toLine.fiscalYear', 'toLine.currency', 'toLine.glAccount',
            'submittedBy:id,name', 'approvedBy:id,name',
        ];
    }

    private function line(int $id): BudgetLine
    {
        $query = BudgetLine::withoutGlobalScope('fiscal_year_context')
            ->with([
                'project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
                'grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            ]);
        if (request()->user()) {
            app(DataScopeService::class)->applyScope($query, request()->user(), 'created_by', 'project_id', null, []);
        }

        return $query->findOrFail($id);
    }

    private function assertCompatible(BudgetLine $from, BudgetLine $to, ?int $requestedProjectId = null): void
    {
        if (! $from->is_active || ! $to->is_active) {
            throw ValidationException::withMessages(['to_budget_line_id' => 'Budget line sumber dan tujuan harus aktif.']);
        }

        $fromRelation = $from->relation_no ?: ($from->project?->relation_no ?: $from->grantAgreement?->relation_no);
        $toRelation = $to->relation_no ?: ($to->project?->relation_no ?: $to->grantAgreement?->relation_no);
        $sameScope = (int) $from->project_id === (int) $to->project_id
            && (int) $from->grant_agreement_id === (int) $to->grant_agreement_id
            && (int) $from->currency_id === (int) $to->currency_id
            && (int) $from->fiscal_year_id === (int) $to->fiscal_year_id
            && trim((string) $fromRelation) === trim((string) $toRelation);

        if (! $sameScope) {
            throw ValidationException::withMessages([
                'to_budget_line_id' => 'Realokasi hanya boleh antar budget line dalam nomor relasi, tahun fiskal, grant, project, dan currency yang sama.',
            ]);
        }
        if ($requestedProjectId && $requestedProjectId !== (int) $from->project_id) {
            throw ValidationException::withMessages(['project_id' => 'Project realokasi harus sama dengan project pada budget line sumber.']);
        }
    }
}
