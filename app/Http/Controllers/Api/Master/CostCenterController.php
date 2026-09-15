<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\CostCenter;
use App\Http\Requests\Master\StoreCostCenterRequest;
use App\Http\Requests\Master\UpdateCostCenterRequest;
use App\Http\Resources\Master\CostCenterResource;

class CostCenterController extends BaseMasterController
{
    protected string $modelClass = CostCenter::class;
    protected string $resourceClass = CostCenterResource::class;
    protected string $storeRequestClass = StoreCostCenterRequest::class;
    protected string $updateRequestClass = UpdateCostCenterRequest::class;
    protected array $searchableColumns = ['code', 'name', 'description'];
    protected array $defaultWith = ['organization', 'department'];
}