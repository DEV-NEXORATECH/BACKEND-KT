<?php

namespace App\Http\Controllers\Api\Timesheet;

use App\Http\Controllers\Controller;
use App\Models\Master\Activity;
use App\Models\Master\Employee;
use App\Models\Master\Project;
use App\Models\ProjectAssignment;
use App\Models\Timesheet\TimesheetEntry;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Approval\ApprovalWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TimesheetEntryController extends Controller
{
    private array $with = ['employee:id,employee_id_number,name,email,department_id', 'employee.department:id,code,name', 'project:id,code,name,program_id', 'project.program:id,code,name', 'activity:id,code,name,project_id', 'donor:id,code,name', 'program:id,code,name', 'department:id,code,name', 'supervisor:id,name,email'];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewAll = $this->userHasPermission($user, 'timesheet.view_all');
        $canViewTeam = $this->userHasPermission($user, 'timesheet.team.view');
        $workerType = $request->string('worker_type', 'internal')->toString();
        $scopeMine = $request->string('scope')->toString() === 'mine';
        if (! in_array($workerType, ['internal', 'external', 'consultant'], true)) {
            $workerType = 'internal';
        }
        $accountWorkerType = $user->worker_type ?: 'internal';
        if ($workerType !== 'internal' && ! $canViewAll && ! $canViewTeam && $workerType !== $accountWorkerType) {
            abort(Response::HTTP_FORBIDDEN, 'Akses external/consultant timesheet hanya tersedia untuk manager atau administrator.');
        }

        $entries = TimesheetEntry::query()
            ->with($this->with)
            ->where('worker_type', $workerType)
            ->when($scopeMine || (! $canViewAll && ! $canViewTeam), fn (Builder $query) => $query->where('user_id', $user->id))
            ->when(! $canViewAll && $canViewTeam, function (Builder $query) use ($user) {
                $projectIds = ProjectAssignment::query()
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->where(function ($assignment) {
                        $assignment->whereNull('assigned_from')->orWhereDate('assigned_from', '<=', now());
                    })
                    ->where(function ($assignment) {
                        $assignment->whereNull('assigned_to')->orWhereDate('assigned_to', '>=', now());
                    })
                    ->pluck('project_id');

                $query->where(function (Builder $scope) use ($user, $projectIds) {
                    $scope->where('supervisor_id', $user->id);
                    if ($projectIds->isNotEmpty()) {
                        $scope->orWhereIn('project_id', $projectIds);
                    }
                });
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->integer('project_id')))
            ->when($request->filled('program_id'), fn (Builder $query) => $query->where('program_id', $request->integer('program_id')))
            ->when($request->filled('donor_id'), fn (Builder $query) => $query->where('donor_id', $request->integer('donor_id')))
            ->when($request->filled('employee_id'), fn (Builder $query) => $query->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('entry_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('entry_date', '<=', $request->string('end_date')))
            ->latest('entry_date')
            ->latest('id')
            ->get();

        $totals = [
            'hours' => round((float) $entries->sum('hours'), 2),
            'billable_hours' => round((float) $entries->where('is_billable', true)->sum('hours'), 2),
            'entries' => $entries->count(),
            'by_employee' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->employee?->name ?? 'Unassigned')->map(fn ($rows, $label) => ['label' => $label, 'hours' => round((float) $rows->sum('hours'), 2), 'entries' => $rows->count()])->values(),
            'by_project' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->project?->name ?? 'Unassigned')->map(fn ($rows, $label) => ['label' => $label, 'hours' => round((float) $rows->sum('hours'), 2), 'entries' => $rows->count()])->values(),
            'by_donor' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->donor?->name ?? 'Unassigned')->map(fn ($rows, $label) => ['label' => $label, 'hours' => round((float) $rows->sum('hours'), 2), 'entries' => $rows->count()])->values(),
        ];

        return response()->json(['success' => true, 'totals' => $totals, 'data' => $entries->map(fn (TimesheetEntry $entry) => $this->format($entry))]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $isPrivileged = $this->userHasPermission($request->user(), 'timesheet.team.view') || $this->userHasPermission($request->user(), 'timesheet.view_all');
        if (! $isPrivileged) {
            $payload['worker_type'] = $request->user()->worker_type ?: 'internal';
        }
        $payload = $this->applyBillingCalculation($payload);
        $employee = Employee::query()->find($payload['employee_id']);
        $project = isset($payload['project_id']) ? Project::query()->with('program.grantAgreement.donor')->find($payload['project_id']) : null;
        $activity = isset($payload['activity_id']) ? Activity::query()->find($payload['activity_id']) : null;

        if ($activity && $project && (int) $activity->project_id !== (int) $project->id) {
            throw ValidationException::withMessages(['activity_id' => 'Activity tidak sesuai dengan project yang dipilih.']);
        }
        if ($project && isset($payload['program_id']) && $payload['program_id'] && (int) $project->program_id !== (int) $payload['program_id']) {
            throw ValidationException::withMessages(['program_id' => 'Program tidak sesuai dengan project yang dipilih.']);
        }
        if ($project && isset($payload['donor_id']) && $payload['donor_id'] && (int) $project->grantAgreement?->donor_id !== (int) $payload['donor_id']) {
            throw ValidationException::withMessages(['donor_id' => 'Donor tidak sesuai dengan project yang dipilih.']);
        }

        if (! $this->userHasPermission($request->user(), 'timesheet.approve') && $employee?->user?->id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh membuat timesheet untuk pegawai lain.');
        }

        $entry = TimesheetEntry::create([
            ...$payload,
            'prepared_signed_at' => ! empty($payload['prepared_signature']) ? now() : null,
            'user_id' => $employee?->user?->id ?? $request->user()->id,
            'department_id' => $payload['department_id'] ?? $employee?->department_id,
            'program_id' => $payload['program_id'] ?? $project?->program_id,
            'donor_id' => $payload['donor_id'] ?? $project?->grantAgreement?->donor_id,
            'status' => 'draft',
        ])->load($this->with);

        return response()->json(['success' => true, 'message' => 'Timesheet entry berhasil dibuat.', 'data' => $this->format($entry)], Response::HTTP_CREATED);
    }

    public function update(Request $request, TimesheetEntry $timesheetEntry): JsonResponse
    {
        if ($timesheetEntry->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Timesheet hanya dapat diubah saat draft.']);
        }
        if (! $this->userHasPermission($request->user(), 'timesheet.approve') && (int) $timesheetEntry->user_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh mengubah timesheet user lain.');
        }

        if (! $request->filled('worker_type')) {
            $request->merge(['worker_type' => $timesheetEntry->worker_type ?: 'internal']);
        }
        $payload = $this->validatePayload($request);
        $payload = $this->applyBillingCalculation($payload);
        $project = isset($payload['project_id']) ? Project::query()->with('grantAgreement')->find($payload['project_id']) : null;
        $activity = isset($payload['activity_id']) ? Activity::query()->find($payload['activity_id']) : null;
        if ($activity && $project && (int) $activity->project_id !== (int) $project->id) {
            throw ValidationException::withMessages(['activity_id' => 'Activity tidak sesuai dengan project yang dipilih.']);
        }
        if ($project && isset($payload['program_id']) && $payload['program_id'] && (int) $project->program_id !== (int) $payload['program_id']) {
            throw ValidationException::withMessages(['program_id' => 'Program tidak sesuai dengan project yang dipilih.']);
        }
        if ($project && isset($payload['donor_id']) && $payload['donor_id'] && (int) $project->grantAgreement?->donor_id !== (int) $payload['donor_id']) {
            throw ValidationException::withMessages(['donor_id' => 'Donor tidak sesuai dengan project yang dipilih.']);
        }
        $timesheetEntry->update($payload);

        return response()->json(['success' => true, 'message' => 'Timesheet entry berhasil diperbarui.', 'data' => $this->format($timesheetEntry->fresh($this->with))]);
    }

    public function submit(Request $request, TimesheetEntry $timesheetEntry, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($timesheetEntry->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Timesheet harus draft untuk submit.']);
        }
        if (! $this->userHasPermission($request->user(), 'timesheet.approve') && (int) $timesheetEntry->user_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh submit timesheet user lain.');
        }

        $timesheetEntry->update(['status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);
        $workflow->start('timesheet', $timesheetEntry, (float) $timesheetEntry->hours, $request->user()->id);

        return response()->json(['success' => true, 'message' => 'Timesheet berhasil disubmit.', 'data' => $this->format($timesheetEntry->fresh($this->with))]);
    }

    public function approve(Request $request, TimesheetEntry $timesheetEntry, ApprovalWorkflowService $workflow): JsonResponse
    {
        return $this->decide($request, $timesheetEntry, 'approved', $workflow);
    }

    public function reject(Request $request, TimesheetEntry $timesheetEntry, ApprovalWorkflowService $workflow): JsonResponse
    {
        return $this->decide($request, $timesheetEntry, 'rejected', $workflow);
    }

    private function decide(Request $request, TimesheetEntry $timesheetEntry, string $decision, ApprovalWorkflowService $workflow): JsonResponse
    {
        if ($timesheetEntry->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Timesheet harus submitted untuk approval.']);
        }
        $data = $request->validate(['notes' => ['nullable', 'string'], 'signature' => ['nullable', 'string']]);
        if ($decision === 'approved') {
            $approval = $workflow->approve('timesheet', $timesheetEntry, $request->user(), $data['notes'] ?? null);
            if ($approval['managed'] && ! $approval['completed']) {
                return response()->json(['success' => true, 'message' => "Approval tahap selesai. Menunggu approver level {$approval['next_level']}.", 'data' => $this->format($timesheetEntry->fresh($this->with))]);
            }
        } else {
            $workflow->reject('timesheet', $timesheetEntry, $request->user(), $data['notes'] ?? 'Rejected');
        }
        $field = $decision === 'approved' ? 'approved' : 'rejected';

        $timesheetEntry->update([
            'status' => $decision,
            "{$field}_by" => $request->user()->id,
            "{$field}_at" => now(),
            'decision_notes' => $data['notes'] ?? null,
            'approved_signature' => $decision === 'approved' ? ($data['signature'] ?? null) : null,
            'approved_signed_at' => $decision === 'approved' && ! empty($data['signature']) ? now() : null,
        ]);

        return response()->json(['success' => true, 'message' => "Timesheet berhasil {$decision}.", 'data' => $this->format($timesheetEntry->fresh($this->with))]);
    }

    public function postLaborCost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'timesheet_ids' => ['required', 'array', 'min:1'],
            'timesheet_ids.*' => ['required', 'integer', 'exists:timesheet_entries,id'],
            'hourly_rate' => ['nullable', 'numeric', 'min:1'],
            'posting_date' => ['nullable', 'date'],
        ]);

        $postingDate = $data['posting_date'] ?? now()->toDateString();
        $overrideRate = isset($data['hourly_rate']) ? (float) $data['hourly_rate'] : null;

        app(AccountingPeriodService::class)->ensureOpen($postingDate, 'posting_date');

        $entries = TimesheetEntry::whereIn('id', $data['timesheet_ids'])
            ->where('status', 'approved')
            ->where('worker_type', 'internal')
            ->whereNull('journal_id')
            ->with('employee:id,hourly_cost_rate')
            ->get();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['timesheet_ids' => 'Tidak ada timesheet berstatus approved yang dipilih.']);
        }

        $processedIds = TimesheetEntry::whereIn('id', $data['timesheet_ids'])->pluck('id');
        $skippedIds = $processedIds->diff($entries->pluck('id'))->values();

        $laborAccount = \App\Models\Master\ChartOfAccount::where('account_type', 'expense')->where('is_header', false)->first();
        $payableAccount = \App\Models\Master\ChartOfAccount::where('account_type', 'liability')->where('is_header', false)->first();

        if (! $laborAccount || ! $payableAccount) {
            throw ValidationException::withMessages(['account' => 'COA expense/liability untuk labor cost belum tersedia.']);
        }

        foreach ($entries as $entry) {
            if (($overrideRate ?? (float) ($entry->employee?->hourly_cost_rate ?? 0)) <= 0) {
                throw ValidationException::withMessages(['hourly_rate' => "Master hourly cost rate belum diisi untuk employee timesheet ID {$entry->id}."]);
            }
        }

        $postedCount = 0;
        $totalCost = 0;

        DB::transaction(function () use ($entries, $postingDate, $overrideRate, $laborAccount, $payableAccount, &$postedCount, &$totalCost, $request) {
            foreach ($entries as $entry) {
                $rate = $overrideRate ?? (float) $entry->employee->hourly_cost_rate;
                $cost = round((float) $entry->hours * $rate, 2);
                $journal = \App\Models\Accounting\Journal::create([
                    'journal_number' => 'LABOR-'.now()->format('YmdHis').'-'.random_int(100, 999),
                    'journal_date' => $postingDate,
                    'journal_type' => 'manual',
                    'reference' => 'TS-'.$entry->id,
                    'description' => 'Alokasi biaya jam kerja: '.$entry->description,
                    'status' => 'posted',
                    'posted_by' => $request->user()->id,
                    'posted_at' => now(),
                ]);

                $journal->lines()->create([
                    'account_id' => $laborAccount->id,
                    'project_id' => $entry->project_id,
                    'donor_id' => $entry->donor_id,
                    'program_id' => $entry->program_id,
                    'department_id' => $entry->department_id,
                    'line_description' => 'Labor cost ('.$entry->hours.' jam)',
                    'debit' => $cost,
                    'credit' => 0,
                    'line_order' => 1,
                ]);

                $journal->lines()->create([
                    'account_id' => $payableAccount->id,
                    'line_description' => 'Accrued labor cost',
                    'debit' => 0,
                    'credit' => $cost,
                    'line_order' => 2,
                ]);

                $entry->update([
                    'status' => 'posted',
                    'journal_id' => $journal->id,
                    'posted_by' => $request->user()->id,
                    'posted_at' => now(),
                ]);
                $postedCount++;
                $totalCost += $cost;
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Berhasil memposting alokasi biaya tenaga kerja untuk {$postedCount} timesheet.",
            'posted_count' => $postedCount,
            'skipped_ids' => $skippedIds->all(),
            'total_cost' => $totalCost,
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'worker_type' => ['sometimes', 'string', 'in:internal,external,consultant'],
            'vendor_name' => ['nullable', 'string', 'max:180'],
            'contract_reference' => ['nullable', 'string', 'max:120'],
            'invoice_reference' => ['nullable', 'string', 'max:120'],
            'billing_mode' => ['nullable', 'string', 'in:daily,hourly'],
            'fee_total' => ['nullable', 'numeric', 'min:0'],
            'work_days' => ['nullable', 'numeric', 'min:0.01'],
            'break_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'normal_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'entry_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['required', 'string'],
            'work_area' => ['nullable', 'string', 'max:120'],
            'workstream' => ['nullable', 'string', 'max:120'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_billable' => ['nullable', 'boolean'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'prepared_signature' => ['nullable', 'string'],
        ]) + ['worker_type' => $request->input('worker_type', 'internal')];
    }

    private function applyBillingCalculation(array $payload): array
    {
        if (($payload['worker_type'] ?? 'internal') !== 'consultant' || empty($payload['fee_total']) || empty($payload['work_days'])) {
            return $payload;
        }

        $fee = (float) $payload['fee_total'];
        $days = (float) $payload['work_days'];
        $hours = (float) ($payload['hours'] ?? 0);
        $mode = $payload['billing_mode'] ?? 'daily';
        $payload['billing_mode'] = $mode;
        $payload['rate_per_day'] = round($fee / $days, 2);
        $payload['rate_per_hour'] = round($fee / ($days * 8), 2);
        $payload['normal_hours'] = min($hours, 8);
        $payload['payable_amount'] = $mode === 'hourly'
            ? round($hours * $payload['rate_per_hour'], 2)
            : round(($hours / 8) * $payload['rate_per_day'], 2);

        return $payload;
    }

    private function userHasPermission($user, string $permission): bool
    {
        return (bool) $user?->hasPermission($permission);
    }

    private function format(TimesheetEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'worker_type' => $entry->worker_type ?: 'internal',
            'vendor_name' => $entry->vendor_name,
            'contract_reference' => $entry->contract_reference,
            'invoice_reference' => $entry->invoice_reference,
            'billing_mode' => $entry->billing_mode,
            'fee_total' => $entry->fee_total,
            'work_days' => $entry->work_days,
            'rate_per_day' => $entry->rate_per_day,
            'rate_per_hour' => $entry->rate_per_hour,
            'break_hours' => $entry->break_hours,
            'normal_hours' => $entry->normal_hours,
            'payable_amount' => $entry->payable_amount,
            'prepared_signature' => $entry->prepared_signature,
            'prepared_signed_at' => $entry->prepared_signed_at,
            'approved_signature' => $entry->approved_signature,
            'approved_signed_at' => $entry->approved_signed_at,
            'employee' => $entry->employee ? ['id' => $entry->employee->id, 'employee_id_number' => $entry->employee->employee_id_number, 'name' => $entry->employee->name, 'email' => $entry->employee->email] : null,
            'entry_date' => $entry->entry_date?->toDateString(),
            'hours' => $entry->hours,
            'description' => $entry->description,
            'work_area' => $entry->work_area,
            'workstream' => $entry->workstream,
            'donor' => $entry->donor ? ['id' => $entry->donor->id, 'code' => $entry->donor->code, 'name' => $entry->donor->name] : null,
            'program' => $entry->program ? ['id' => $entry->program->id, 'code' => $entry->program->code, 'name' => $entry->program->name] : null,
            'project' => $entry->project ? ['id' => $entry->project->id, 'code' => $entry->project->code, 'name' => $entry->project->name] : null,
            'activity' => $entry->activity ? ['id' => $entry->activity->id, 'code' => $entry->activity->code, 'name' => $entry->activity->name] : null,
            'department' => $entry->department ? ['id' => $entry->department->id, 'code' => $entry->department->code, 'name' => $entry->department->name] : null,
            'is_billable' => $entry->is_billable,
            'supervisor' => $entry->supervisor ? ['id' => $entry->supervisor->id, 'name' => $entry->supervisor->name] : null,
            'status' => $entry->status,
            'journal_id' => $entry->journal_id,
            'submitted_at' => $entry->submitted_at?->toIso8601String(),
            'approved_at' => $entry->approved_at?->toIso8601String(),
            'rejected_at' => $entry->rejected_at?->toIso8601String(),
            'posted_at' => $entry->posted_at?->toIso8601String(),
            'decision_notes' => $entry->decision_notes,
        ];
    }
}
