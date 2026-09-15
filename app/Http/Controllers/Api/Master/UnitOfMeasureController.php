<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\UnitOfMeasure;
use App\Http\Requests\Master\StoreUnitOfMeasureRequest;
use App\Http\Requests\Master\UpdateUnitOfMeasureRequest;
use App\Http\Resources\Master\UnitOfMeasureResource;

class UnitOfMeasureController extends BaseMasterController
{
    protected string $modelClass = UnitOfMeasure::class;
    protected string $resourceClass = UnitOfMeasureResource::class;
    protected string $storeRequestClass = StoreUnitOfMeasureRequest::class;
    protected string $updateRequestClass = UpdateUnitOfMeasureRequest::class;
    protected array $searchableColumns = ['code', 'name', 'category'];
    protected array $defaultWith = [];
}