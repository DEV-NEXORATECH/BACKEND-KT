<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\FiscalYear;
use App\Http\Requests\Master\StoreFiscalYearRequest;
use App\Http\Requests\Master\UpdateFiscalYearRequest;
use App\Http\Resources\Master\FiscalYearResource;

class FiscalYearController extends BaseMasterController
{
    protected string $modelClass = FiscalYear::class;
    protected string $resourceClass = FiscalYearResource::class;
    protected string $storeRequestClass = StoreFiscalYearRequest::class;
    protected string $updateRequestClass = UpdateFiscalYearRequest::class;
    protected array $searchableColumns = ['year', 'name'];
    protected array $defaultWith = [];
}