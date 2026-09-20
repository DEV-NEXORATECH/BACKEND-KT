<?php

namespace App\Http\Controllers\Api\Timesheet;

use App\Http\Controllers\Controller;
use App\Models\Master\Activity;
use App\Models\Master\Employee;
use App\Models\Master\Project;
use App\Models\Timesheet\TimesheetEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function submit(Request $request, TimesheetEntry $timesheetEntry): JsonResponse
    {
        if ($timesheetEntry->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Timesheet harus draft untuk submit.']);
        }
        if (! $this->userHasPermission($request->user(), 'timesheet.approve') && (int) $timesheetEntry->user_id !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'Tidak boleh submit timesheet user lain.');
        }

        $timesheetEntry->update(['status' => 'submitted', 'submitted_by' => $request->user()->id, 'submitted_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Timesheet berhasil disubmit.', 'data' => $this->format($timesheetEntry->fresh($this->with))]);
    }

    public function approve(Request $request, TimesheetEntry $timesheetEntry): JsonResponse
    {
        return $this->decide($request, $timesheetEntry, 'approved');
    }

    public function reject(Request $request, TimesheetEntry $timesheetEntry): JsonResponse
    {
        return $this->decide($request, $timesheetEntry, 'rejected');
    }

    private function decide(Request $request, TimesheetEntry $timesheetEntry, string $decision): JsonResponse
    {
        if ($timesheetEntry->status !== 'submitted') {
            throw ValidationException::withMessages(['status' => 'Timesheet harus submitted untuk approval.']);
        }
        $data = $request->validate(['notes' => ['nullable', 'string']]);
        $field = $decision === 'approved' ? 'approved' : 'rejected';

        $timesheetEntry->update([
            'status' => $decision,
            "{$field}_by" => $request->user()->id,
            "{$field}_at" => now(),
            'decision_notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => "Timesheet berhasil {$decision}.", 'data' => $this->format($timesheetEntry->fresh($this->with))]);
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
            'submitted_at' => $entry->submitted_at?->toIso8601String(),
            'approved_at' => $entry->approved_at?->toIso8601String(),
            'rejected_at' => $entry->rejected_at?->toIso8601String(),
            'decision_notes' => $entry->decision_notes,
        ];
    }
}
