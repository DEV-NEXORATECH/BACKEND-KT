<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreProcurementCategoryRequest;
use App\Http\Requests\Master\UpdateProcurementCategoryRequest;
use App\Http\Resources\Master\ProcurementMasterResource;
use App\Models\Master\ProcurementCategory;

class ProcurementCategoryController extends BaseMasterController
{
    protected string $modelClass = ProcurementCategory::class;
    protected string $resourceClass = ProcurementMasterResource::class;
    protected string $storeRequestClass = StoreProcurementCategoryRequest::class;
    protected string $updateRequestClass = UpdateProcurementCategoryRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['parent'];
}
