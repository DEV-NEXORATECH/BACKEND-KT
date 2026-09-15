<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Organization;
use App\Http\Requests\Master\StoreOrganizationRequest;
use App\Http\Requests\Master\UpdateOrganizationRequest;
use App\Http\Resources\Master\OrganizationResource;

class OrganizationController extends BaseMasterController
{
    protected string $modelClass = Organization::class;
    protected string $resourceClass = OrganizationResource::class;
    protected string $storeRequestClass = StoreOrganizationRequest::class;
    protected string $updateRequestClass = UpdateOrganizationRequest::class;
    protected array $searchableColumns = ['code', 'name', 'legal_name', 'npwp'];
    protected array $defaultWith = ['officeLocations', 'departments'];
}