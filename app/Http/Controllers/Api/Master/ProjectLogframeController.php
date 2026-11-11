<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Master\Project;
use App\Models\Master\ProjectLogframe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectLogframeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'relation_no' => 'nullable|string|max:100',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
        ]);

        $relationNo = trim((string) ($filters['relation_no'] ?? ''));
        if (! empty($filters['project_id'])) {
            $this->projectForRelation((int) $filters['project_id'], $relationNo);
        }

        $query = ProjectLogframe::with('project:id,code,relation_no,name')
            ->when($filters['project_id'] ?? null, fn ($builder, $projectId) => $builder->where('project_id', $projectId))
            ->when($relationNo !== '', function ($builder) use ($relationNo) {
                $builder->where(function ($scope) use ($relationNo) {
                    $scope->where('relation_no', $relationNo)
                        ->orWhereHas('project', fn ($project) => $project->where('relation_no', $relationNo));
                });
            })
            ->when($filters['fiscal_year_id'] ?? null, fn ($builder, $fiscalYearId) => $builder->where('fiscal_year_id', $fiscalYearId));

        return response()->json([
            'success' => true,
            'scope' => ['relation_no' => $relationNo ?: null, 'project_id' => $filters['project_id'] ?? null],
            'data' => $query->latest()->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true));
        $project = $this->projectForRelation((int) $data['project_id'], (string) ($data['relation_no'] ?? ''));
        $data['relation_no'] = $project->relation_no ?: ($data['relation_no'] ?? null);
        $data['code'] = $data['code'] ?? $this->nextCode((int) $data['project_id'], $data['level']);

        return response()->json(['success' => true, 'data' => ProjectLogframe::create($data)], 201);
    }

    public function generateActivities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'relation_no' => 'nullable|string|max:100',
        ]);
        $project = $this->projectForRelation((int) $data['project_id'], (string) ($data['relation_no'] ?? ''));
        $relationNo = $project->relation_no ?: ($data['relation_no'] ?? null);

        $created = DB::transaction(function () use ($data, $relationNo) {
            $outputs = ProjectLogframe::query()
                ->where('project_id', $data['project_id'])
                ->where('level', 'output')
                ->orderBy('id')
                ->get();
            $saved = [];

            foreach ($outputs as $output) {
                $prefix = strtoupper($output->code).'-ACT-';
                if (ProjectLogframe::query()->where('project_id', $data['project_id'])->where('level', 'activity')->where('code', 'like', $prefix.'%')->exists()) {
                    continue;
                }
                $saved[] = ProjectLogframe::create([
                    'project_id' => $data['project_id'],
                    'relation_no' => $relationNo,
                    'level' => 'activity',
                    'code' => $prefix.'01',
                    'description' => 'Aktivitas pelaksanaan: '.$output->description,
                    'indicator' => $output->indicator,
                    'baseline' => $output->baseline,
                    'target' => $output->target,
                    'period_start' => $output->period_start,
                    'period_end' => $output->period_end,
                ]);
            }

            return collect($saved);
        });

        return response()->json([
            'success' => true,
            'data' => $created,
            'created' => $created->count(),
            'message' => $created->count().' activity generated.',
        ]);
    }

    public function update(Request $request, ProjectLogframe $projectLogframe): JsonResponse
    {
        $data = $request->validate($this->rules(false));
        if (isset($data['project_id']) && (int) $data['project_id'] !== (int) $projectLogframe->project_id) {
            throw ValidationException::withMessages(['project_id' => 'Project Framework tidak dapat dipindahkan ke project lain.']);
        }
        $project = $this->projectForRelation((int) $projectLogframe->project_id, (string) ($data['relation_no'] ?? ''));
        $data['relation_no'] = $project->relation_no ?: ($data['relation_no'] ?? $projectLogframe->relation_no);
        $projectLogframe->update($data);

        return response()->json(['success' => true, 'data' => $projectLogframe->fresh()]);
    }

    public function destroy(ProjectLogframe $projectLogframe): JsonResponse
    {
        $projectLogframe->delete();

        return response()->json(['success' => true]);
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'relation_no' => 'nullable|string|max:100',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        $project = $this->projectForRelation((int) $data['project_id'], (string) ($data['relation_no'] ?? ''));
        $relationNo = $project->relation_no ?: ($data['relation_no'] ?? null);
        $workbook = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $workbook->getSheetByName('FrameworkLogframe') ?: $workbook->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $created = [];
        $level = null;
        $counters = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $first = trim((string) ($row['A'] ?? ''));
            $second = trim((string) ($row['B'] ?? ''));
            $marker = strtolower($first);
            if (in_array($marker, ['impact', 'outcome', 'output', 'activity'], true)) {
                $level = $marker;
                $counters[$level] = ($counters[$level] ?? 0) + 1;
                continue;
            }
            if (! $level || ($first === '' && $second === '')) {
                continue;
            }
            if (in_array(strtolower(trim((string) ($row['C'] ?? ''))), ['achieved', 'planned', 'source (where evidence will be sought)'], true)) {
                continue;
            }
            $description = $first !== '' ? $first : $second;
            $indicator = $first !== '' ? $second : '';
            if (mb_strlen($description) < 3) {
                $errors[] = "Row {$index}: description is too short.";
                continue;
            }
            $code = strtoupper($level).'-'.($counters[$level] ?? 1);
            $counters[$level] = ($counters[$level] ?? 1) + 1;
            $created[] = [
                'project_id' => $data['project_id'],
                'relation_no' => $relationNo,
                'level' => $level,
                'code' => $code,
                'description' => $description,
                'indicator' => $indicator,
                'baseline' => is_numeric($row['D'] ?? null) ? $row['D'] : null,
                'target' => is_numeric($row['E'] ?? null) ? $row['E'] : null,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
        $saved = DB::transaction(fn () => collect($created)->map(fn ($row) => ProjectLogframe::create($row))->values());

        return response()->json(['success' => true, 'data' => $saved, 'imported' => $saved->count()]);
    }

    private function projectForRelation(int $projectId, string $relationNo = ''): Project
    {
        $project = Project::query()->findOrFail($projectId);
        $relationNo = trim($relationNo);
        if ($relationNo !== '' && $project->relation_no && strcasecmp($project->relation_no, $relationNo) !== 0) {
            throw ValidationException::withMessages([
                'relation_no' => 'Nomor Relasi tidak sesuai dengan project yang dipilih.',
            ]);
        }

        return $project;
    }

    private function nextCode(int $projectId, string $level): string
    {
        $prefix = ['impact' => 'IMP', 'outcome' => 'OUT', 'output' => 'OUTP', 'activity' => 'ACT'][$level] ?? 'FW';
        $used = ProjectLogframe::query()->where('project_id', $projectId)->where('level', $level)->pluck('code');
        $max = 0;
        foreach ($used as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/i', (string) $code, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return $prefix.'-'.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    private function rules(bool $creating): array
    {
        return [
            'project_id' => $creating ? 'required|exists:projects,id' : 'sometimes|exists:projects,id',
            'relation_no' => 'nullable|string|max:100',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'level' => $creating ? 'required|in:impact,outcome,output,activity' : 'sometimes|in:impact,outcome,output,activity',
            'code' => $creating ? 'nullable|string|max:50' : 'sometimes|string|max:50',
            'description' => $creating ? 'required|string' : 'sometimes|string',
            'indicator' => 'nullable|string|max:255',
            'baseline' => 'nullable|numeric',
            'target' => 'nullable|numeric',
            'actual' => 'nullable|numeric',
            'unit' => 'nullable|string|max:50',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
        ];
    }
}
