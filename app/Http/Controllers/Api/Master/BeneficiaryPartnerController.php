<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\BeneficiaryPartner;
use App\Http\Requests\Master\StoreBeneficiaryPartnerRequest;
use App\Http\Requests\Master\UpdateBeneficiaryPartnerRequest;
use App\Http\Resources\Master\BeneficiaryPartnerResource;

class BeneficiaryPartnerController extends BaseMasterController
{
    protected string $modelClass = BeneficiaryPartner::class;
    protected string $resourceClass = BeneficiaryPartnerResource::class;
    protected string $storeRequestClass = StoreBeneficiaryPartnerRequest::class;
    protected string $updateRequestClass = UpdateBeneficiaryPartnerRequest::class;
    protected array $searchableColumns = ['code', 'name', 'pic_name'];
    protected array $defaultWith = [];
}