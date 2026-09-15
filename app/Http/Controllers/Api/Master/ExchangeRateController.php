<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\ExchangeRate;
use App\Http\Requests\Master\StoreExchangeRateRequest;
use App\Http\Requests\Master\UpdateExchangeRateRequest;
use App\Http\Resources\Master\ExchangeRateResource;

class ExchangeRateController extends BaseMasterController
{
    protected string $modelClass = ExchangeRate::class;
    protected string $resourceClass = ExchangeRateResource::class;
    protected string $storeRequestClass = StoreExchangeRateRequest::class;
    protected string $updateRequestClass = UpdateExchangeRateRequest::class;
    protected array $searchableColumns = ['source'];
    protected array $defaultWith = ['fromCurrency', 'toCurrency'];
}