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
<<<<<<< HEAD
    protected array $searchableColumns = ['line_code', 'description'];
=======
    protected array $searchableColumns = ['line_code', 'description', 'activity_code', 'activity_name', 'sub_output_code'];
>>>>>>> f43874195204991ff2ac8f360cd50738da12243e
    protected array $defaultWith = ['fiscalYear', 'grantAgreement', 'project', 'activity', 'budgetCategory', 'unitOfMeasure', 'glAccount', 'currency'];
}
