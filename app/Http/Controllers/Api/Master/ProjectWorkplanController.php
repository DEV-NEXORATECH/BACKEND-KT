<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\ProjectWorkplan;
use App\Models\Master\Activity;
use App\Models\Master\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectWorkplanController extends Controller
{
    public function index(Request $request)
    {
        $rows = ProjectWorkplan::with(['project:id,code,name,grant_agreement_id', 'project.grantAgreement:id,donor_id,grant_no,agreement_name', 'grantAgreement:id,donor_id,grant_no,agreement_name', 'donor:id,code,name', 'activity:id,code,name'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('fiscal_year_id'), function ($q) use ($request) {
                $fiscalYearId = $request->integer('fiscal_year_id');
                $q->where(function ($scope) use ($fiscalYearId) {
                    // Keep legacy rows visible while new rows are explicitly scoped.
                    $scope->where('fiscal_year_id', $fiscalYearId)
                        ->orWhere(function ($legacy) use ($fiscalYearId) {
                            $legacy->whereNull('fiscal_year_id')
                                ->whereHas('project.fiscalYears', fn ($project) => $project->whereKey($fiscalYearId));
                        });
                });
            })
            ->orderByRaw('COALESCE(start_date, "9999-12-31") ASC')->orderBy('id', 'asc')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id', 'donor_id' => 'nullable|exists:donors,id', 'grant_agreement_id' => 'nullable|exists:grant_agreements,id', 'fiscal_year_id' => 'nullable|exists:fiscal_years,id', 'activity_id' => 'nullable|exists:activities,id',
            'output_code' => 'nullable|string|max:50', 'activity_code' => 'nullable|string|max:50',
            'activity' => 'required|string|max:500', 'responsible' => 'nullable|string|max:255',
            'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
            'baseline_start_date' => 'nullable|date', 'baseline_end_date' => 'nullable|date|after_or_equal:baseline_start_date',
            'status' => 'nullable|in:planned,in_progress,completed,delayed,cancelled', 'progress' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string', 'periods' => 'nullable|array',
        ]);
        [$data['grant_agreement_id'], $data['donor_id']] = $this->resolveProjectFunding($data['project_id'], $data['grant_agreement_id'] ?? null, $data['donor_id'] ?? null);
        $this->validateProjectFiscalYear($data['project_id'], $data['fiscal_year_id'] ?? null);
        $this->validateActivityProject($data['project_id'], $data['activity_id'] ?? null);
        if (empty($data['baseline_start_date']) && !empty($data['start_date'])) {
            $data['baseline_start_date'] = $data['start_date'];
        }
        if (empty($data['baseline_end_date']) && !empty($data['end_date'])) {
            $data['baseline_end_date'] = $data['end_date'];
        }
        $workplan = ProjectWorkplan::create($data)->load(['project:id,code,name,grant_agreement_id', 'project.grantAgreement:id,donor_id,grant_no,agreement_name', 'grantAgreement:id,donor_id,grant_no,agreement_name', 'donor:id,code,name', 'activity:id,code,name']);
        return response()->json(['success' => true, 'data' => $workplan], 201);
    }

    public function update(Request $request, ProjectWorkplan $projectWorkplan)
    {
        $data = $request->validate([
            'donor_id' => 'nullable|exists:donors,id', 'grant_agreement_id' => 'nullable|exists:grant_agreements,id', 'fiscal_year_id' => 'nullable|exists:fiscal_years,id', 'activity_id' => 'nullable|exists:activities,id', 'output_code' => 'nullable|string|max:50', 'activity_code' => 'nullable|string|max:50',
            'activity' => 'sometimes|string|max:500', 'responsible' => 'nullable|string|max:255', 'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'baseline_start_date' => 'nullable|date', 'baseline_end_date' => 'nullable|date|after_or_equal:baseline_start_date', 'status' => 'nullable|in:planned,in_progress,completed,delayed,cancelled',
            'progress' => 'nullable|numeric|min:0|max:100', 'notes' => 'nullable|string', 'periods' => 'nullable|array',
        ]);
        if (array_key_exists('donor_id', $data) || array_key_exists('grant_agreement_id', $data)) {
            [$data['grant_agreement_id'], $data['donor_id']] = $this->resolveProjectFunding($projectWorkplan->project_id, $data['grant_agreement_id'] ?? $projectWorkplan->grant_agreement_id, $data['donor_id'] ?? $projectWorkplan->donor_id);
        }
        $this->validateProjectFiscalYear($projectWorkplan->project_id, $data['fiscal_year_id'] ?? $request->query('fiscal_year_id'));
        $this->validateActivityProject($projectWorkplan->project_id, $data['activity_id'] ?? $projectWorkplan->activity_id);
        $projectWorkplan->update($data);
        return response()->json(['success' => true, 'data' => $projectWorkplan->fresh()->load(['project:id,code,name,grant_agreement_id', 'project.grantAgreement:id,donor_id,grant_no,agreement_name', 'grantAgreement:id,donor_id,grant_no,agreement_name', 'donor:id,code,name', 'activity:id,code,name'])]);
    }

    public function destroy(ProjectWorkplan $projectWorkplan) { $projectWorkplan->delete(); return response()->json(['success' => true]); }

    public function import(Request $request)
    {
        $request->validate(['project_id' => 'required|exists:projects,id', 'fiscal_year_id' => 'nullable|exists:fiscal_years,id', 'file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $this->validateProjectFiscalYear($request->integer('project_id'), $request->input('fiscal_year_id'));
        $workbook = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $workbook->getSheetByName('Workplan') ?: $workbook->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $created = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            if ($index < 10) continue;
            $activity = trim((string) ($row['B'] ?? $row['C'] ?? ''));
            if ($activity === '') continue;
            if (mb_strlen($activity) < 3) { $errors[] = "Row {$index}: activity is too short."; continue; }
            $created[] = [
                'project_id' => $request->integer('project_id'), 'grant_agreement_id' => $this->resolveProjectFunding($request->integer('project_id'), null, null)[0], 'donor_id' => $this->resolveProjectFunding($request->integer('project_id'), null, null)[1], 'fiscal_year_id' => $request->input('fiscal_year_id'), 'output_code' => trim((string) ($row['A'] ?? '')),
                'activity_code' => trim((string) ($row['B'] ?? '')), 'activity_id' => $this->activityIdFromRow($row), 'activity' => $activity,
                'responsible' => trim((string) ($row['D'] ?? '')), 'start_date' => $this->dateValue($row['E'] ?? null),
                'end_date' => $this->dateValue($row['F'] ?? null), 'baseline_start_date' => $this->dateValue($row['E'] ?? null),
                'baseline_end_date' => $this->dateValue($row['F'] ?? null), 'status' => 'planned', 'progress' => 0,
            ];
        }
        if ($errors) throw ValidationException::withMessages(['file' => $errors]);
        foreach ($created as $data) {
            try {
                $this->validateActivityProject($data['project_id'], $data['activity_id'] ?? null);
            } catch (ValidationException $exception) {
                $errors = array_merge($errors, $exception->errors()['activity_id'] ?? ['Invalid activity/project relationship.']);
            }
        }
        if ($errors) throw ValidationException::withMessages(['file' => $errors]);
        $saved = DB::transaction(fn () => collect($created)->map(fn ($data) => ProjectWorkplan::create($data))->values());
        $saved->load(['project:id,code,name,grant_agreement_id', 'project.grantAgreement:id,donor_id,grant_no,agreement_name', 'grantAgreement:id,donor_id,grant_no,agreement_name', 'donor:id,code,name', 'activity:id,code,name']);
        return response()->json(['success' => true, 'data' => $saved, 'imported' => $saved->count()]);
    }

    private function activityIdFromRow(array $row): ?int
    {
        $value = $row['G'] ?? $row['H'] ?? null;
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function validateActivityProject(int $projectId, $activityId): void
    {
        if ($activityId === null || $activityId === '') return;
        $valid = Activity::query()->whereKey($activityId)->where('project_id', $projectId)->exists();
        if (!$valid) {
            throw ValidationException::withMessages([
                'activity_id' => 'The selected activity must belong to the selected project.',
            ]);
        }
    }

    private function resolveProjectFunding(int $projectId, $grantId, $donorId): array
    {
        $project = Project::query()->with('grantAgreement:id,donor_id,grant_no,agreement_name')->findOrFail($projectId);
        $projectGrantId = $project->grant_agreement_id;
        $projectDonorId = $project->grantAgreement?->donor_id;
        if (! $projectGrantId) {
            throw ValidationException::withMessages(['grant_agreement_id' => 'Project harus terhubung ke Grant Agreement sebelum aktivitas dibuat.']);
        }
        if (! $projectDonorId) {
            throw ValidationException::withMessages(['donor_id' => 'Grant Agreement project harus terhubung ke Donor sebelum aktivitas dibuat.']);
        }
        if ($grantId !== null && $grantId !== '' && (int) $grantId !== (int) $projectGrantId) {
            throw ValidationException::withMessages(['grant_agreement_id' => 'Grant aktivitas harus sama dengan Grant Agreement pada project.']);
        }
        if ($donorId !== null && $donorId !== '' && (int) $donorId !== (int) $projectDonorId) {
            throw ValidationException::withMessages(['donor_id' => 'Donor aktivitas harus sama dengan donor pada Grant Agreement project.']);
        }
        return [
            $grantId !== null && $grantId !== '' ? (int) $grantId : ($projectGrantId ? (int) $projectGrantId : null),
            $donorId !== null && $donorId !== '' ? (int) $donorId : ($projectDonorId ? (int) $projectDonorId : null),
        ];
    }

    private function validateProjectFiscalYear(int $projectId, $fiscalYearId): void
    {
        if ($fiscalYearId === null || $fiscalYearId === '') return;
        $project = Project::query()
            ->with(['fiscalYears:id', 'grantAgreement:id,fiscal_year_id'])
            ->findOrFail($projectId);
        $hasPivotYear = $project->fiscalYears->contains(fn ($year) => (int) $year->id === (int) $fiscalYearId);
        $hasGrantYear = (int) ($project->grantAgreement?->fiscal_year_id ?? 0) === (int) $fiscalYearId;
        if (!$hasPivotYear && !$hasGrantYear) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => 'The selected project is not available in the selected fiscal year.',
            ]);
        }
    }

    private function dateValue($value): ?string
    {
        if (!$value) return null;
        if (is_numeric($value)) return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
        try { return date('Y-m-d', strtotime((string) $value)); } catch (\Throwable) { return null; }
    }
}
