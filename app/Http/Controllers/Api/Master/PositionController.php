<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StorePositionRequest;
use App\Http\Requests\Master\UpdatePositionRequest;
use App\Http\Resources\Master\PositionResource;
use App\Models\Master\Position;

class PositionController extends BaseMasterController
{
    protected string $modelClass = Position::class;
    protected string $resourceClass = PositionResource::class;
    protected string $storeRequestClass = StorePositionRequest::class;
    protected string $updateRequestClass = UpdatePositionRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['department:id,code,name'];
}
