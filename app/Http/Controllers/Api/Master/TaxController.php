<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Tax;
use App\Http\Requests\Master\StoreTaxRequest;
use App\Http\Requests\Master\UpdateTaxRequest;
use App\Http\Resources\Master\TaxResource;

class TaxController extends BaseMasterController
{
    protected string $modelClass = Tax::class;
    protected string $resourceClass = TaxResource::class;
    protected string $storeRequestClass = StoreTaxRequest::class;
    protected string $updateRequestClass = UpdateTaxRequest::class;
    protected array $searchableColumns = ['code', 'name', 'tax_type'];
    protected array $defaultWith = ['salesGlAccount', 'purchaseGlAccount'];
}