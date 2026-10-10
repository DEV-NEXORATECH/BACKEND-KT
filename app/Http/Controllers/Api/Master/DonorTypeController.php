<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Requests\Master\StoreDonorTypeRequest;
use App\Http\Requests\Master\UpdateDonorTypeRequest;
use App\Http\Resources\Master\DonorTypeResource;
use App\Models\Master\DonorType;

class DonorTypeController extends BaseMasterController
{
    protected string $modelClass = DonorType::class;
    protected string $resourceClass = DonorTypeResource::class;
    protected string $storeRequestClass = StoreDonorTypeRequest::class;
    protected string $updateRequestClass = UpdateDonorTypeRequest::class;
    protected array $searchableColumns = ['code', 'name', 'description'];
    protected array $defaultWith = ['fiscalYear'];
}
