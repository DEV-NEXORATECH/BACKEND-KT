<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ExpenseClass;
use App\Http\Requests\Master\StoreExpenseClassRequest;
use App\Http\Requests\Master\UpdateExpenseClassRequest;
use App\Http\Resources\Master\ExpenseClassResource;

class ExpenseClassController extends BaseMasterController
{
    protected string $modelClass = ExpenseClass::class;
    protected string $resourceClass = ExpenseClassResource::class;
    protected string $storeRequestClass = StoreExpenseClassRequest::class;
    protected string $updateRequestClass = UpdateExpenseClassRequest::class;
    protected array $searchableColumns = ['code', 'name', 'detail_type'];
    protected array $defaultWith = ['donor'];
}
