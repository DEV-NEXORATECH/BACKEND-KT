<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\PettyCash;
use App\Http\Requests\Master\StorePettyCashRequest;
use App\Http\Requests\Master\UpdatePettyCashRequest;
use App\Http\Resources\Master\PettyCashResource;

class PettyCashController extends BaseMasterController
{
    protected string $modelClass = PettyCash::class;
    protected string $resourceClass = PettyCashResource::class;
    protected string $storeRequestClass = StorePettyCashRequest::class;
    protected string $updateRequestClass = UpdatePettyCashRequest::class;
    protected array $searchableColumns = ['code', 'name', 'custodian_name'];
    protected array $defaultWith = ['organization', 'officeLocation', 'currency', 'glAccount'];
}