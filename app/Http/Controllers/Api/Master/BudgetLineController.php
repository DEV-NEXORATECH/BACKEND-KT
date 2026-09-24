<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BudgetLine;
use App\Http\Requests\Master\StoreBudgetLineRequest;
use App\Http\Requests\Master\UpdateBudgetLineRequest;
use App\Http\Resources\Master\BudgetLineResource;

class BudgetLineController extends BaseMasterController
{
    protected string $modelClass = BudgetLine::class;
    protected string $resourceClass = BudgetLineResource::class;
    protected string $storeRequestClass = StoreBudgetLineRequest::class;
    protected string $updateRequestClass = UpdateBudgetLineRequest::class;
    protected array $searchableColumns = ['line_code', 'description'];
    protected array $defaultWith = ['grantAgreement', 'project', 'budgetCategory', 'unitOfMeasure', 'glAccount', 'currency'];
}
