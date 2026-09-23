<?php

namespace App\Http\Controllers\Api\Timesheet;

use App\Http\Controllers\Controller;
use App\Models\Master\Activity;
use App\Models\Master\Employee;
use App\Models\Master\Project;
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
        $canApprove = $this->userHasPermission($user, 'timesheet.approve');

        $entries = TimesheetEntry::query()
            ->with($this->with)
            ->when(! $canApprove, fn (Builder $query) => $query->where('user_id', $user->id))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('project_id'), fn (Builder $query) => $query->where('project_id', $request->integer('project_id')))
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
        ];

        return response()->json(['success' => true, 'totals' => $totals, 'data' => $entries->map(fn (TimesheetEntry $entry) => $this->format($entry))]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $employee = Employee::query()->find($payload['employee_id']);
        $project = isset($payload['project_id']) ? Project::query()->with('program.grantAgreement.donor')->find($payload['project_id']) : null;
        $activity = isset($payload['activity_id']) ? Activity::query()->find($payload['activity_id']) : null;

        if ($activity && $project && (int) $activity->project_id !== (int) $project->id) {
            throw ValidationException::withMessages(['activity_id' => 'Activity tidak sesuai dengan project yang dipilih.']);
        }

        if (! $this->userHasPermission($request->user(), 'timesheet.approve') && $employee?->user?->id !== $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh membuat timesheet untuk pegawai lain.');
        }

        $entry = TimesheetEntry::create([
            ...$payload,
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

        $payload = $this->validatePayload($request);
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
        $data = $request->validate(['notes' => ['nullable', 'string']]);
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
            'entry_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'description' => ['required', 'string'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_billable' => ['nullable', 'boolean'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function userHasPermission($user, string $permission): bool
    {
        return (bool) $user?->role?->permissions()->where('slug', $permission)->exists();
    }

    private function format(TimesheetEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'employee' => $entry->employee ? ['id' => $entry->employee->id, 'employee_id_number' => $entry->employee->employee_id_number, 'name' => $entry->employee->name, 'email' => $entry->employee->email] : null,
            'entry_date' => $entry->entry_date?->toDateString(),
            'hours' => $entry->hours,
            'description' => $entry->description,
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
