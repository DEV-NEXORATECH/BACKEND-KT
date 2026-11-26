<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BudgetLine;
use App\Http\Requests\Master\StoreBudgetLineRequest;
use App\Http\Requests\Master\UpdateBudgetLineRequest;
use App\Http\Resources\Master\BudgetLineResource;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
use App\Models\Master\FiscalYear;
use App\Services\Budget\BudgetLineImportService;
use App\Services\Master\MasterExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BudgetLineController extends BaseMasterController
{
    protected string $modelClass = BudgetLine::class;
    protected string $resourceClass = BudgetLineResource::class;
    protected string $storeRequestClass = StoreBudgetLineRequest::class;
    protected string $updateRequestClass = UpdateBudgetLineRequest::class;
    protected array $searchableColumns = ['line_code', 'description', 'budget_name'];
    protected array $defaultWith = ['fiscalYear', 'grantAgreement.donor', 'grantAgreement.bankAccount.glAccount', 'project.bankAccount.glAccount', 'project.grantAgreement.donor', 'project.grantAgreement.bankAccount.glAccount', 'activity', 'budgetCategory', 'unitOfMeasure', 'glAccount', 'currency'];

    protected function defaultRelations(): array
    {
        return [
            'fiscalYear',
            'grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreement.donor',
            'grantAgreement.bankAccount.glAccount',
            'project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'project.bankAccount.glAccount',
            'project.grantAgreement.donor',
            'project.grantAgreement.bankAccount.glAccount',
            'activity',
            'budgetCategory',
            'unitOfMeasure',
            'glAccount',
            'currency',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $relationNo = trim((string) $request->query('relation_no', ''));
        if (in_array(mb_strtolower($relationNo), ['belum tersedia', 'tanpa nomor relasi'], true)) {
            return response()->json([
                'success' => true,
                'message' => 'Nomor relasi placeholder diabaikan.',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => min(max((int) $request->query('per_page', 10), 1), 500),
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                ],
            ]);
        }

        $filters = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'relation_no' => 'nullable|string|max:150',
            'budget_name' => 'nullable|string|max:150',
        ]);

        if (! empty($filters['project_id']) && ! empty($filters['relation_no'])) {
            $projectRelationNo = Project::query()
                ->withoutGlobalScope('fiscal_year_context')
                ->whereKey($filters['project_id'])
                ->value('relation_no');
            if ($projectRelationNo && strcasecmp($projectRelationNo, trim($filters['relation_no'])) !== 0) {
                throw ValidationException::withMessages([
                    'relation_no' => 'Nomor Relasi tidak sesuai dengan project yang dipilih.',
                ]);
            }
        }

        if (! empty($filters['grant_agreement_id']) && ! empty($filters['relation_no'])) {
            $grantRelationNo = GrantAgreement::query()
                ->withoutGlobalScope('fiscal_year_context')
                ->whereKey($filters['grant_agreement_id'])
                ->value('relation_no');
            if ($grantRelationNo && strcasecmp($grantRelationNo, trim($filters['relation_no'])) !== 0) {
                throw ValidationException::withMessages([
                    'relation_no' => 'Nomor Relasi tidak sesuai dengan Grant Agreement yang dipilih.',
                ]);
            }
        }

        return parent::index($request);
    }

    public function relations(Request $request): JsonResponse
    {
        $query = BudgetLine::query()->withoutGlobalScopes()
            ->selectRaw('COALESCE(NULLIF(relation_no, ""), "Tanpa Nomor Relasi") as relation')
            ->selectRaw('COUNT(*) as item_count, SUM(base_amount) as total_amount, MAX(project_id) as project_id, MAX(grant_agreement_id) as grant_agreement_id, MAX(fiscal_year_id) as fiscal_year_id')
            ->when($request->filled('fiscal_year_id'), fn ($q) => $q->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($inner) use ($request) {
                $term = '%'.trim((string) $request->query('search')).'%';
                $inner->where('relation_no', 'like', $term)->orWhere('line_code', 'like', $term)->orWhere('description', 'like', $term);
            }))
            ->groupBy('relation')->orderBy('relation');
        $page = $query->paginate(min(max((int) $request->query('per_page', 10), 1), 100));
        return response()->json(['success' => true, 'data' => collect($page->items())->map(function ($item) {
            $project = $item->project_id ? Project::withoutGlobalScopes()->with(['grantAgreement.donor', 'fiscalYears'])->find($item->project_id) : null;
            $grant = $item->grant_agreement_id
                ? GrantAgreement::withoutGlobalScopes()->with('donor')->find($item->grant_agreement_id)
                : $project?->grantAgreement;
            $donor = $grant?->donor;
            $fiscalYearId = $item->fiscal_year_id ?: ($grant?->fiscal_year_id ?: $project?->fiscalYears?->first()?->id);
            $fiscalYear = $fiscalYearId ? FiscalYear::query()->find($fiscalYearId) : null;
            return [
            'relation_no' => $item->relation,
            'item_count' => (int) $item->item_count,
            'total_amount' => (float) $item->total_amount,
            'project_id' => $item->project_id,
            'grant_agreement_id' => $item->grant_agreement_id,
            'fiscal_year_id' => $fiscalYearId,
            'fiscal_year' => $fiscalYear ? ['id' => $fiscalYear->id, 'year' => $fiscalYear->year, 'name' => $fiscalYear->name] : null,
            'donor' => $donor ? ['id' => $donor->id, 'code' => $donor->code, 'name' => $donor->name] : null,
            'grant_agreement' => $grant ? ['id' => $grant->id, 'grant_no' => $grant->grant_no, 'agreement_name' => $grant->agreement_name, 'status' => $grant->status] : null,
            'is_active' => $grant ? (bool) $grant->is_active : true,
        ];
        })->values(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function template(Request $request, MasterExportService $exportService)
    {
        $format = (string) $request->query('format', 'xlsx');

        return app(BudgetLineImportService::class)->downloadTemplate($format, $exportService);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'project_id' => 'nullable|integer|exists:projects,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'relation_no' => 'nullable|string|max:100',
            'fiscal_year_id' => 'nullable|integer|exists:fiscal_years,id',
            'coa_code' => 'nullable|string|max:50',
            'gl_account_id' => 'nullable|integer|exists:chart_of_accounts,id',
        ]);

        $defaults = [
            'project_id' => $validated['project_id'] ?? null,
            'grant_agreement_id' => $validated['grant_agreement_id'] ?? null,
            'relation_no' => $validated['relation_no'] ?? null,
            'fiscal_year_id' => $validated['fiscal_year_id'] ?? ($request->header('X-Fiscal-Year-Id') ?: null),
            'coa_code' => $validated['coa_code'] ?? null,
            'gl_account_id' => $validated['gl_account_id'] ?? null,
        ];

        $result = app(BudgetLineImportService::class)->import($request->file('file'), $defaults);
        $message = "{$result['created']} data berhasil diimport, {$result['skipped']} data duplikat/tidak valid dilewati.";

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result,
        ]);
    }
}
