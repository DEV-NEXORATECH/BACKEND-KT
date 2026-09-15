<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BudgetCategory;
use App\Http\Requests\Master\StoreBudgetCategoryRequest;
use App\Http\Requests\Master\UpdateBudgetCategoryRequest;
use App\Http\Resources\Master\BudgetCategoryResource;

class BudgetCategoryController extends BaseMasterController
{
    protected string $modelClass = BudgetCategory::class;
    protected string $resourceClass = BudgetCategoryResource::class;
    protected string $storeRequestClass = StoreBudgetCategoryRequest::class;
    protected string $updateRequestClass = UpdateBudgetCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['parent'];
}