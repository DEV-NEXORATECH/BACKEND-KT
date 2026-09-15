<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\FundingSource;
use App\Http\Requests\Master\StoreFundingSourceRequest;
use App\Http\Requests\Master\UpdateFundingSourceRequest;
use App\Http\Resources\Master\FundingSourceResource;

class FundingSourceController extends BaseMasterController
{
    protected string $modelClass = FundingSource::class;
    protected string $resourceClass = FundingSourceResource::class;
    protected string $storeRequestClass = StoreFundingSourceRequest::class;
    protected string $updateRequestClass = UpdateFundingSourceRequest::class;
    protected array $searchableColumns = ['code', 'name', 'funding_type'];
    protected array $defaultWith = ['grantAgreements'];
}