<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\GrantAgreement;
use App\Http\Requests\Master\StoreGrantAgreementRequest;
use App\Http\Requests\Master\UpdateGrantAgreementRequest;
use App\Http\Resources\Master\GrantAgreementResource;

class GrantAgreementController extends BaseMasterController
{
    protected string $modelClass = GrantAgreement::class;
    protected string $resourceClass = GrantAgreementResource::class;
    protected string $storeRequestClass = StoreGrantAgreementRequest::class;
    protected string $updateRequestClass = UpdateGrantAgreementRequest::class;
    protected array $searchableColumns = ['grant_no', 'agreement_name'];
    protected array $defaultWith = ['donor', 'fundingSource', 'currency', 'bankAccount'];
}