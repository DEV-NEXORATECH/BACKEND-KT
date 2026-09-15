<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\AccountingPeriod;
use App\Http\Requests\Master\StoreAccountingPeriodRequest;
use App\Http\Requests\Master\UpdateAccountingPeriodRequest;
use App\Http\Resources\Master\AccountingPeriodResource;

class AccountingPeriodController extends BaseMasterController
{
    protected string $modelClass = AccountingPeriod::class;
    protected string $resourceClass = AccountingPeriodResource::class;
    protected string $storeRequestClass = StoreAccountingPeriodRequest::class;
    protected string $updateRequestClass = UpdateAccountingPeriodRequest::class;
    protected array $searchableColumns = ['name'];
    protected array $defaultWith = ['fiscalYear'];
}