<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ExpenseCategory;
use App\Http\Requests\Master\StoreExpenseCategoryRequest;
use App\Http\Requests\Master\UpdateExpenseCategoryRequest;
use App\Http\Resources\Master\ExpenseCategoryResource;

class ExpenseCategoryController extends BaseMasterController
{
    protected string $modelClass = ExpenseCategory::class;
    protected string $resourceClass = ExpenseCategoryResource::class;
    protected string $storeRequestClass = StoreExpenseCategoryRequest::class;
    protected string $updateRequestClass = UpdateExpenseCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['parent', 'defaultGlAccount'];
}