<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Currency;
use App\Http\Requests\Master\StoreCurrencyRequest;
use App\Http\Requests\Master\UpdateCurrencyRequest;
use App\Http\Resources\Master\CurrencyResource;

class CurrencyController extends BaseMasterController
{
    protected string $modelClass = Currency::class;
    protected string $resourceClass = CurrencyResource::class;
    protected string $storeRequestClass = StoreCurrencyRequest::class;
    protected string $updateRequestClass = UpdateCurrencyRequest::class;
    protected array $searchableColumns = ['code', 'name', 'symbol'];
    protected array $defaultWith = [];
}