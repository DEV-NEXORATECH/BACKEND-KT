<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BudgetLine;
use App\Http\Requests\Master\StoreBudgetLineRequest;
use App\Http\Requests\Master\UpdateBudgetLineRequest;
use App\Http\Resources\Master\BudgetLineResource;
use App\Models\Master\Project;
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
    protected array $defaultWith = ['fiscalYear', 'grantAgreement', 'project', 'activity', 'budgetCategory', 'unitOfMeasure', 'glAccount', 'currency'];

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'project_id' => 'nullable|integer|exists:projects,id',
            'relation_no' => 'nullable|string|max:100',
        ]);

        if (! empty($filters['project_id']) && ! empty($filters['relation_no'])) {
            $projectRelationNo = Project::query()->whereKey($filters['project_id'])->value('relation_no');
            if ($projectRelationNo && strcasecmp($projectRelationNo, trim($filters['relation_no'])) !== 0) {
                throw ValidationException::withMessages([
                    'relation_no' => 'Nomor Relasi tidak sesuai dengan project yang dipilih.',
                ]);
            }
        }

        return parent::index($request);
    }
}
