<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BudgetLine;
use App\Http\Requests\Master\StoreBudgetLineRequest;
use App\Http\Requests\Master\UpdateBudgetLineRequest;
use App\Http\Resources\Master\BudgetLineResource;
use App\Models\Master\GrantAgreement;
use App\Models\Master\Project;
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
    protected array $searchableColumns = ['line_code', 'description'];
    protected array $defaultWith = ['fiscalYear', 'grantAgreement.bankAccount.glAccount', 'project.bankAccount.glAccount', 'project.grantAgreement.bankAccount.glAccount', 'activity', 'budgetCategory', 'unitOfMeasure', 'glAccount', 'currency'];

    protected function defaultRelations(): array
    {
        return [
            'fiscalYear',
            'grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreement.bankAccount.glAccount',
            'project' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'project.bankAccount.glAccount',
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
        $filters = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'grant_agreement_id' => 'nullable|integer|exists:grant_agreements,id',
            'relation_no' => 'nullable|string|max:100',
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
