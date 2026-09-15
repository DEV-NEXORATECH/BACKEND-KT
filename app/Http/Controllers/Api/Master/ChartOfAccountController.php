<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ChartOfAccount;
use App\Http\Requests\Master\StoreChartOfAccountRequest;
use App\Http\Requests\Master\UpdateChartOfAccountRequest;
use App\Http\Resources\Master\ChartOfAccountResource;

class ChartOfAccountController extends BaseMasterController
{
    protected string $modelClass = ChartOfAccount::class;
    protected string $resourceClass = ChartOfAccountResource::class;
    protected string $storeRequestClass = StoreChartOfAccountRequest::class;
    protected string $updateRequestClass = UpdateChartOfAccountRequest::class;
    protected array $searchableColumns = ['code', 'name'];
    protected array $defaultWith = ['parent'];
}