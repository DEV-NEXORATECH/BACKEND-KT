<?php

namespace App\Http\Controllers\Api\Timesheet;

use App\Http\Controllers\Controller;
use App\Models\Master\Activity;
use App\Models\Master\Employee;
use App\Models\Master\Project;
use App\Models\ProjectAssignment;
use App\Models\Procurement\SupplierInvoice;
use App\Models\Timesheet\TimesheetEntry;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\Timesheet\TimesheetCalculationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TimesheetEntryController extends Controller
{
    private array $with = [
        'employee:id,employee_id_number,name,email,department_id,employment_type,contract_total_fee,contract_total_days,daily_cost_rate,hourly_cost_rate,default_rate_scheme',
        'employee.department:id,code,name',
        'project:id,code,name,program_id',
        'project.program:id,code,name',
        'activity:id,code,name,project_id',
        'donor:id,code,name',
        'program:id,code,name',
        'department:id,code,name',
        'supervisor:id,name,email',
    ];

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
            ->when($request->filled('task_type'), fn (Builder $query) => $query->where('task_type', $request->string('task_type')))
            ->when($request->filled('rate_scheme'), fn (Builder $query) => $query->where('rate_scheme', $request->string('rate_scheme')))
            ->when($request->filled('start_date'), fn (Builder $query) => $query->whereDate('entry_date', '>=', $request->string('start_date')))
            ->when($request->filled('end_date'), fn (Builder $query) => $query->whereDate('entry_date', '<=', $request->string('end_date')))
            ->latest('entry_date')
            ->latest('id')
            ->get();

        $totals = [
            'hours' => round((float) $entries->sum('hours'), 2),
            'billable_hours' => round((float) $entries->sum(fn (TimesheetEntry $e) => (float) ($e->billable_hours ?? ($e->is_billable ? $e->hours : 0))), 2),
            'calculated_amount' => round((float) $entries->sum('calculated_amount'), 2),
            'entries' => $entries->count(),
            'by_employee' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->employee?->name ?? 'Unassigned')->map(fn ($rows, $label) => [
                'label' => $label,
                'hours' => round((float) $rows->sum('hours'), 2),
                'billable_hours' => round((float) $rows->sum(fn ($r) => (float) ($r->billable_hours ?? $r->hours)), 2),
                'amount' => round((float) $rows->sum('calculated_amount'), 2),
                'entries' => $rows->count(),
            ])->values(),
            'by_project' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->project?->name ?? 'Unassigned')->map(fn ($rows, $label) => [
                'label' => $label,
                'hours' => round((float) $rows->sum('hours'), 2),
                'amount' => round((float) $rows->sum('calculated_amount'), 2),
                'entries' => $rows->count(),
            ])->values(),
            'by_donor' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->donor?->name ?? 'Unassigned')->map(fn ($rows, $label) => [
                'label' => $label,
                'hours' => round((float) $rows->sum('hours'), 2),
                'amount' => round((float) $rows->sum('calculated_amount'), 2),
                'entries' => $rows->count(),
            ])->values(),
            'by_rate_scheme' => $entries->groupBy(fn (TimesheetEntry $entry) => $entry->rate_scheme ?? 'daily_capped_8h')->map(fn ($rows, $scheme) => [
                'scheme' => $scheme,
                'hours' => round((float) $rows->sum('hours'), 2),
                'billable_hours' => round((float) $rows->sum(fn ($r) => (float) ($r->billable_hours ?? $r->hours)), 2),
                'amount' => round((float) $rows->sum('calculated_amount'), 2),
                'entries' => $rows->count(),
            ])->values(),
        ];

        return response()->json(['success' => true, 'totals' => $totals, 'data' => $entries->map(fn (TimesheetEntry $entry) => $this->format($entry))]);
    }

    public function store(Request $request, TimesheetCalculationService $calculationService): JsonResponse
    {
        if (! $request->filled('employee_id')) {
            $request->merge(['employee_id' => $request->user()->employee?->id]);
        }
        $payload = $this->validatePayload($request);
        $isPrivileged = $this->userHasPermission($request->user(), 'timesheet.team.view') || $this->userHasPermission($request->user(), 'timesheet.view_all');
        if (! $isPrivileged) {
            $payload['worker_type'] = $request->user()->worker_type ?: 'internal';
        }
        if (($payload['worker_type'] ?? 'internal') === 'external') {
            $payload['is_billable'] = ($payload['time_category'] ?? 'working_time') === 'working_time';
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

        $calc = $calculationService->calculate(
            $employee,
            (float) $payload['hours'],
            $payload['rate_scheme'] ?? null,
            isset($payload['applied_rate']) ? (float) $payload['applied_rate'] : null
        );
        if (empty($payload['is_billable'])) {
            $calc['billable_hours'] = 0;
            $calc['calculated_amount'] = 0;
        }

        $entry = TimesheetEntry::create([
            ...$payload,
            'task_type' => $payload['task_type'] ?? (! empty($payload['activity_id']) ? 'project_activity' : 'general'),
            'rate_scheme' => $calc['rate_scheme'],
            'applied_rate' => $calc['applied_rate'],
            'billable_hours' => $calc['billable_hours'],
            'calculated_amount' => $calc['calculated_amount'],
            'prepared_signed_at' => ! empty($payload['prepared_signature']) ? now() : null,
            'user_id' => $employee?->user?->id ?? $request->user()->id,
            'department_id' => $payload['department_id'] ?? $employee?->department_id,
            'program_id' => $payload['program_id'] ?? $project?->program_id,
            'donor_id' => $payload['donor_id'] ?? $project?->grantAgreement?->donor_id,
            'status' => 'draft',
        ])->load($this->with);

        return response()->json(['success' => true, 'message' => 'Timesheet entry berhasil dibuat.', 'data' => $this->format($entry)], Response::HTTP_CREATED);
    }

    public function update(Request $request, TimesheetEntry $timesheetEntry, TimesheetCalculationService $calculationService): JsonResponse
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
        if (($payload['worker_type'] ?? 'internal') === 'external') {
            $payload['is_billable'] = ($payload['time_category'] ?? 'working_time') === 'working_time';
        }
        $payload = $this->applyBillingCalculation($payload);
        $employee = Employee::query()->find($payload['employee_id']);
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

        $calc = $calculationService->calculate(
            $employee,
            (float) $payload['hours'],
            $payload['rate_scheme'] ?? $timesheetEntry->rate_scheme,
            isset($payload['applied_rate']) ? (float) $payload['applied_rate'] : (float) $timesheetEntry->applied_rate
        );
        if (empty($payload['is_billable'])) {
            $calc['billable_hours'] = 0;
            $calc['calculated_amount'] = 0;
        }

        $timesheetEntry->update([
            ...$payload,
            'task_type' => $payload['task_type'] ?? (! empty($payload['activity_id']) ? 'project_activity' : 'general'),
            'rate_scheme' => $calc['rate_scheme'],
            'applied_rate' => $calc['applied_rate'],
            'billable_hours' => $calc['billable_hours'],
            'calculated_amount' => $calc['calculated_amount'],
        ]);

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

    public function postLaborCost(Request $request, TimesheetCalculationService $calculationService): JsonResponse
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
            ->with(['employee:id,employee_id_number,name,hourly_cost_rate,daily_cost_rate,default_rate_scheme'])
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
            $effectiveRate = $overrideRate ?? (float) ($entry->applied_rate ?: ($entry->employee?->hourly_cost_rate ?: ($entry->employee?->daily_cost_rate ? $entry->employee->daily_cost_rate / 8 : 0)));
            if ($effectiveRate <= 0 && ((float) ($entry->calculated_amount ?? 0) <= 0)) {
                throw ValidationException::withMessages(['hourly_rate' => "Master hourly cost rate belum diisi untuk employee timesheet ID {$entry->id}."]);
            }
        }

        $postedCount = 0;
        $totalCost = 0;

        DB::transaction(function () use ($entries, $postingDate, $overrideRate, $laborAccount, $payableAccount, &$postedCount, &$totalCost, $request, $calculationService) {
            foreach ($entries as $entry) {
                if ($overrideRate !== null) {
                    $calc = $calculationService->calculate($entry->employee, (float) $entry->hours, $entry->rate_scheme, $overrideRate);
                    $cost = $calc['calculated_amount'];
                    $usedRate = $calc['applied_rate'];
                    $usedHours = $calc['billable_hours'];
                } elseif ((float) ($entry->calculated_amount ?? 0) > 0) {
                    $cost = (float) $entry->calculated_amount;
                    $usedRate = (float) $entry->applied_rate;
                    $usedHours = (float) ($entry->billable_hours ?? $entry->hours);
                } else {
                    $calc = $calculationService->calculate($entry->employee, (float) $entry->hours, $entry->rate_scheme);
                    $cost = $calc['calculated_amount'];
                    $usedRate = $calc['applied_rate'];
                    $usedHours = $calc['billable_hours'];
                }

                $journal = \App\Models\Accounting\Journal::create([
                    'journal_number' => 'LABOR-'.now()->format('YmdHis').'-'.random_int(100, 999),
                    'journal_date' => $postingDate,
                    'journal_type' => 'manual',
                    'reference' => 'TS-'.$entry->id,
                    'description' => 'Alokasi biaya jam kerja: '.$entry->description,
                    'status' => 'posted',
                    'posted_by' => $request->user()->id,
                    'posted_at' => now(),
                    'source_type' => 'timesheet_entry', 'source_id' => $entry->id,
                ]);

                $journal->lines()->create([
                    'account_id' => $laborAccount->id,
                    'project_id' => $entry->project_id,
                    'donor_id' => $entry->donor_id,
                    'program_id' => $entry->program_id,
                    'department_id' => $entry->department_id,
                    'line_description' => 'Labor cost ('.$usedHours.' jam @ Rp '.number_format($usedRate, 0, ',', '.').')',
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
                    'applied_rate' => $usedRate,
                    'billable_hours' => $usedHours,
                    'calculated_amount' => $cost,
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

    public function createExternalInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'period' => ['required', 'date_format:Y-m'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
        ]);

        $period = \Carbon\Carbon::createFromFormat('Y-m', $data['period']);
        $entries = TimesheetEntry::query()
            ->where('employee_id', $data['employee_id'])
            ->where('worker_type', 'external')
            ->where('status', 'approved')
            ->where('is_billable', true)
            ->whereNull('supplier_invoice_id')
            ->whereBetween('entry_date', [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()])
            ->with(['project:id,code,name', 'activity:id,code,name'])
            ->orderBy('entry_date')
            ->get();

        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['period' => 'Tidak ada timesheet External approved dan belum ditagihkan pada periode ini.']);
        }

        $total = round((float) $entries->sum('calculated_amount'), 2);
        if ($total <= 0) {
            throw ValidationException::withMessages(['period' => 'Tarif External belum tersedia sehingga invoice tidak dapat dibuat.']);
        }

        $invoice = DB::transaction(function () use ($data, $entries, $total, $period, $request) {
            do {
                $number = 'TS-EXT-'.$period->format('Ym').'-'.random_int(10000, 99999);
            } while (SupplierInvoice::withTrashed()->where('invoice_number', $number)->exists());

            $invoice = SupplierInvoice::create([
                'vendor_id' => $data['vendor_id'],
                'invoice_number' => $number,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'status' => 'matched',
                'match_status' => 'matched',
                'total_amount' => $total,
                'notes' => 'Generated from approved External Timesheet for '.$period->translatedFormat('F Y').'.',
                'created_by' => $request->user()->id,
            ]);

            foreach ($entries as $entry) {
                $invoice->lines()->create([
                    'item_description' => trim(implode(' — ', array_filter([
                        $entry->entry_date->format('d M Y'),
                        $entry->project?->name,
                        $entry->activity?->name ?: $entry->description,
                    ]))),
                    'quantity' => $entry->billable_hours ?? $entry->hours,
                    'unit_price' => $entry->applied_rate,
                    'total_amount' => $entry->calculated_amount,
                ]);
            }

            TimesheetEntry::whereIn('id', $entries->pluck('id'))->update([
                'supplier_invoice_id' => $invoice->id,
                'invoice_reference' => $number,
                'updated_at' => now(),
            ]);

            return $invoice;
        });

        return response()->json([
            'success' => true,
            'message' => 'Draft AP invoice External Timesheet berhasil dibuat.',
            'data' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => (float) $invoice->total_amount,
                'status' => $invoice->status,
                'entry_count' => $entries->count(),
            ],
        ], Response::HTTP_CREATED);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'fiscal_year_id' => ['sometimes', 'nullable', 'integer', 'exists:fiscal_years,id'],
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
            'time_category' => ['nullable', 'string', 'in:working_time,sick_leave,annual_leave,absence,public_holiday'],
            'donor_id' => ['nullable', 'integer', 'exists:donors,id'],
            'program_id' => ['nullable', 'integer', 'exists:programs,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'task_type' => ['nullable', 'string', 'in:project_activity,general'],
            'rate_scheme' => ['nullable', 'string', 'in:daily_capped_8h,hourly_unlimited'],
            'applied_rate' => ['nullable', 'numeric', 'min:0'],
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
            'fiscal_year_id' => $entry->fiscal_year_id,
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
            'employee' => $entry->employee ? [
                'id' => $entry->employee->id,
                'employee_id_number' => $entry->employee->employee_id_number,
                'name' => $entry->employee->name,
                'email' => $entry->employee->email,
                'employment_type' => $entry->employee->employment_type ?? 'internal',
                'contract_total_fee' => $entry->employee->contract_total_fee ? (float) $entry->employee->contract_total_fee : null,
                'contract_total_days' => $entry->employee->contract_total_days ? (int) $entry->employee->contract_total_days : null,
                'daily_cost_rate' => $entry->employee->daily_cost_rate ? (float) $entry->employee->daily_cost_rate : null,
                'hourly_cost_rate' => $entry->employee->hourly_cost_rate ? (float) $entry->employee->hourly_cost_rate : null,
                'default_rate_scheme' => $entry->employee->default_rate_scheme ?? 'daily_capped_8h',
            ] : null,
            'entry_date' => $entry->entry_date?->toDateString(),
            'hours' => (float) $entry->hours,
            'task_type' => $entry->task_type ?? 'project_activity',
            'rate_scheme' => $entry->rate_scheme ?? ($entry->employee?->default_rate_scheme ?? 'daily_capped_8h'),
            'applied_rate' => $entry->applied_rate !== null ? (float) $entry->applied_rate : null,
            'billable_hours' => $entry->billable_hours !== null ? (float) $entry->billable_hours : (float) $entry->hours,
            'calculated_amount' => $entry->calculated_amount !== null ? (float) $entry->calculated_amount : 0.0,
            'description' => $entry->description,
            'work_area' => $entry->work_area,
            'workstream' => $entry->workstream,
            'time_category' => $entry->time_category ?: 'working_time',
            'donor' => $entry->donor ? ['id' => $entry->donor->id, 'code' => $entry->donor->code, 'name' => $entry->donor->name] : null,
            'program' => $entry->program ? ['id' => $entry->program->id, 'code' => $entry->program->code, 'name' => $entry->program->name] : null,
            'project' => $entry->project ? ['id' => $entry->project->id, 'code' => $entry->project->code, 'name' => $entry->project->name] : null,
            'activity' => $entry->activity ? ['id' => $entry->activity->id, 'code' => $entry->activity->code, 'name' => $entry->activity->name] : null,
            'department' => $entry->department ? ['id' => $entry->department->id, 'code' => $entry->department->code, 'name' => $entry->department->name] : null,
            'is_billable' => $entry->is_billable,
            'supervisor' => $entry->supervisor ? ['id' => $entry->supervisor->id, 'name' => $entry->supervisor->name] : null,
            'status' => $entry->status,
            'journal_id' => $entry->journal_id,
            'supplier_invoice_id' => $entry->supplier_invoice_id,
            'submitted_at' => $entry->submitted_at?->toIso8601String(),
            'approved_at' => $entry->approved_at?->toIso8601String(),
            'rejected_at' => $entry->rejected_at?->toIso8601String(),
            'posted_at' => $entry->posted_at?->toIso8601String(),
            'decision_notes' => $entry->decision_notes,
        ];
    }
}
