<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Donor;
use App\Http\Requests\Master\StoreDonorRequest;
use App\Http\Requests\Master\UpdateDonorRequest;
use App\Http\Resources\Master\DonorResource;

class DonorController extends BaseMasterController
{
    protected string $modelClass = Donor::class;
    protected string $resourceClass = DonorResource::class;
    protected string $storeRequestClass = StoreDonorRequest::class;
    protected string $updateRequestClass = UpdateDonorRequest::class;
    protected array $searchableColumns = ['code', 'name', 'contact_person', 'email', 'country'];
    protected array $defaultWith = ['defaultCurrency', 'grantAgreements'];
}