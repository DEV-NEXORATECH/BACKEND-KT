<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\OfficeLocation;
use App\Http\Requests\Master\StoreOfficeLocationRequest;
use App\Http\Requests\Master\UpdateOfficeLocationRequest;
use App\Http\Resources\Master\OfficeLocationResource;

class OfficeLocationController extends BaseMasterController
{
    protected string $modelClass = OfficeLocation::class;
    protected string $resourceClass = OfficeLocationResource::class;
    protected string $storeRequestClass = StoreOfficeLocationRequest::class;
    protected string $updateRequestClass = UpdateOfficeLocationRequest::class;
    protected array $searchableColumns = ['code', 'name', 'pic_name', 'email'];
    protected array $defaultWith = ['organization'];
}