<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreProcurementItemRequest;
use App\Http\Requests\Master\UpdateProcurementItemRequest;
use App\Http\Resources\Master\ProcurementMasterResource;
use App\Models\Master\ProcurementItem;

class ProcurementItemController extends BaseMasterController
{
    protected string $modelClass = ProcurementItem::class;
    protected string $resourceClass = ProcurementMasterResource::class;
    protected string $storeRequestClass = StoreProcurementItemRequest::class;
    protected string $updateRequestClass = UpdateProcurementItemRequest::class;
    protected array $searchableColumns = ['code', 'name', 'description'];
    protected array $defaultWith = ['category', 'unitOfMeasure'];
}
