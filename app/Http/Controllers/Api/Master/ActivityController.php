<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Activity;
use App\Http\Requests\Master\StoreActivityRequest;
use App\Http\Requests\Master\UpdateActivityRequest;
use App\Http\Resources\Master\ActivityResource;

class ActivityController extends BaseMasterController
{
    protected string $modelClass = Activity::class;
    protected string $resourceClass = ActivityResource::class;
    protected string $storeRequestClass = StoreActivityRequest::class;
    protected string $updateRequestClass = UpdateActivityRequest::class;
    protected array $searchableColumns = ['code', 'name', 'pic_name'];
    protected array $defaultWith = ['project'];
}